<?php
/**
 * News edits (modes 13 and 14) must round-trip text that was stored with
 * htmlentities(). Saving the edit form again used to encode &uuml; as
 * &amp;uuml;, so German articles showed the entity text after a normal edit.
 * Run: php tests/news_reedit_test.php
 */

$root = dirname(__DIR__);
$admin = $root . '/admin.php';
$failures = 0;

function assert_true($cond, $message)
{
    global $failures;
    if (!$cond) {
        $failures++;
        fwrite(STDERR, "FAIL: $message\n");
    } else {
        fwrite(STDOUT, "OK: $message\n");
    }
}

function run_case($name, $get, $post, $expect)
{
    global $admin;

    $dir = sys_get_temp_dir() . '/newsreedit_' . bin2hex(random_bytes(4));
    mkdir($dir . '/includes', 0700, true);
    $log = $dir . '/sql.log';
    file_put_contents($log, '');

    $config = <<<'PHP'
<?php
$login = true;
$session_right = (int)getenv('TEST_RIGHT');
$session_id = 1;
$session_user = 'admin';
$gcon = 'stub';
if (!defined('CMS_SERV_NAME')) {
    define('CMS_SERV_NAME', 'Test');
}
function mysql_real_escape_string($string, $link = null) {
    return (string)$string;
}
function mysql_query($sql, $link = null) {
    $log = getenv('SQL_LOG');
    file_put_contents($log, $sql . "\n----\n", FILE_APPEND);
    if (stripos($sql, 'SELECT') === 0 || stripos(ltrim($sql), 'SELECT') === 0) {
        return 'row';
    }
    return 'updated';
}
function mysql_fetch_assoc($result) {
    return false;
}
function mysql_num_rows($result) {
    return 0;
}
PHP;
    file_put_contents($dir . '/includes/config.php', $config);
    file_put_contents($dir . '/includes/head.php', "<?php\n");
    file_put_contents($dir . '/includes/foot.php', "<?php\n");
    if (!file_exists($dir . '/admin.php')) {
        symlink($admin, $dir . '/admin.php');
    }

    $runner = $dir . '/runner.php';
    file_put_contents($runner, <<<'PHP'
<?php
$_GET = json_decode(getenv('TEST_GET'), true);
$_POST = json_decode(getenv('TEST_POST'), true);
if (!is_array($_GET)) {
    $_GET = array();
}
if (!is_array($_POST)) {
    $_POST = array();
}
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = empty($_POST) ? 'GET' : 'POST';
ob_start();
include 'admin.php';
$out = ob_get_clean();
file_put_contents(getenv('TEST_OUT'), $out);
PHP
    );

    $env = array();
    foreach (array_merge($_SERVER, $_ENV) as $key => $value) {
        if (is_string($key) && is_string($value)) {
            $env[$key] = $value;
        }
    }
    $env['SQL_LOG'] = $log;
    $env['TEST_OUT'] = $dir . '/out.html';
    $env['TEST_GET'] = json_encode($get);
    $env['TEST_POST'] = json_encode($post);
    $env['TEST_RIGHT'] = '4';

    $cmd = 'php -d auto_prepend_file= ' . escapeshellarg($runner);
    $descriptors = array(
        1 => array('pipe', 'w'),
        2 => array('pipe', 'w'),
    );
    $proc = proc_open($cmd, $descriptors, $pipes, $dir, $env);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    $html = file_exists($dir . '/out.html') ? file_get_contents($dir . '/out.html') : '';
    $sqlLog = file_get_contents($log);
    $table = ((string)$get['mode'] === '14') ? 'hottest_news' : 'news';

    assert_true($code === 0, "$name exits 0 (got $code) stderr=" . trim($stderr) . ' stdout=' . trim($stdout));
    assert_true(stripos($sqlLog, 'UPDATE ' . $table) !== false, "$name updates $table");
    assert_true(strpos($html, 'erfolgreich ge') !== false, "$name success message");
    assert_true(strpos($sqlLog, $expect) !== false, "$name stores " . json_encode($expect) . ' log=' . json_encode($sqlLog));
    assert_true(strpos($sqlLog, '&amp;uuml;') === false, "$name does not double-encode uuml");
    assert_true(strpos($sqlLog, '&amp;amp;') === false, "$name does not double-encode amp");

    if (is_dir($dir)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            if ($file->isLink() || $file->isFile()) {
                unlink($file->getPathname());
            } else {
                rmdir($file->getPathname());
            }
        }
        rmdir($dir);
    }
}

$stored = 'Patch f&uuml;r den Server &amp; mehr';
$once = 'Sch&ouml;ne Gr&uuml;&szlig;e';

run_case(
    'mode 13 resave keeps stored entities',
    array('mode' => '13', 'id' => '7'),
    array(
        'title' => 'Patch notes',
        'type' => '0',
        'img' => 'banner',
        'text' => $stored,
    ),
    $stored
);

run_case(
    'mode 13 fresh umlauts encode once',
    array('mode' => '13', 'id' => '7'),
    array(
        'title' => 'Patch notes',
        'type' => '1',
        'img' => 'banner',
        'text' => 'Schöne Grüße',
    ),
    $once
);

run_case(
    'mode 14 resave keeps stored entities',
    array('mode' => '14', 'id' => '3'),
    array(
        'title' => 'Slider',
        'img' => 'slide',
        'text' => $stored,
    ),
    $stored
);

run_case(
    'mode 14 fresh ampersand encodes once',
    array('mode' => '14', 'id' => '3'),
    array(
        'title' => 'Slider',
        'img' => 'slide',
        'text' => 'Tom & Jerry',
    ),
    'Tom &amp; Jerry'
);

if ($failures > 0) {
    fwrite(STDERR, "$failures assertion(s) failed\n");
    exit(1);
}

fwrite(STDOUT, "all news re-edit assertions passed\n");
exit(0);
