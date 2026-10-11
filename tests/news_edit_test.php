<?php
/**
 * News edit forms (modes 13 and 14) must not rewrite paragraph breaks as <br />.
 * Run: php tests/news_edit_test.php
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

function textarea_text($html)
{
    if (!preg_match('/<textarea[^>]*\bname="text"[^>]*>(.*?)<\/textarea>/s', $html, $m)) {
        return null;
    }
    return $m[1];
}

function run_case($name, $get, $post, $expect)
{
    global $admin;

    $dir = sys_get_temp_dir() . '/newsedit_' . bin2hex(random_bytes(4));
    mkdir($dir . '/includes', 0700, true);
    $log = $dir . '/sql.log';
    file_put_contents($log, '');

    $storedText = isset($expect['stored_text']) ? $expect['stored_text'] : "Hello\nWorld";
    $storedTitle = isset($expect['stored_title']) ? $expect['stored_title'] : 'Patch notes';
    $storedImage = isset($expect['stored_image']) ? $expect['stored_image'] : 'banner.png';
    $storedHot = isset($expect['stored_hot']) ? $expect['stored_hot'] : '0';
    $table = (isset($get['mode']) && (string)$get['mode'] === '14') ? 'hottest_news' : 'news';

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
    if ($result !== 'row') {
        return false;
    }
    $row = array(
        'id' => '7',
        'ID' => '7',
        'title' => getenv('TEST_TITLE'),
        'text' => getenv('TEST_TEXT'),
        'image' => getenv('TEST_IMAGE'),
        'hot' => getenv('TEST_HOT'),
    );
    return $row;
}
function mysql_num_rows($result) {
    return 1;
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
    $env['TEST_RIGHT'] = isset($expect['right']) ? (string)$expect['right'] : '4';
    $env['TEST_TEXT'] = $storedText;
    $env['TEST_TITLE'] = $storedTitle;
    $env['TEST_IMAGE'] = $storedImage;
    $env['TEST_HOT'] = $storedHot;

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

    assert_true($code === 0, "$name exits 0 (got $code) stderr=" . trim($stderr) . ' stdout=' . trim($stdout));

    if (!empty($expect['form'])) {
        $body = textarea_text($html);
        assert_true($body !== null, "$name renders the text textarea");
        if ($body !== null) {
            assert_true(strpos($body, '<br') === false, "$name textarea does not contain br tags: " . json_encode($body));
            assert_true($body === $storedText, "$name textarea keeps stored text " . json_encode($storedText) . ' got ' . json_encode($body));
        }
    }

    if (!empty($expect['update_contains'])) {
        assert_true(stripos($sqlLog, 'UPDATE ' . $table) !== false, "$name updates $table");
        assert_true(strpos($sqlLog, $expect['update_contains']) !== false, "$name UPDATE contains " . json_encode($expect['update_contains']) . ' log=' . json_encode($sqlLog));
    }
    if (!empty($expect['update_excludes'])) {
        assert_true(strpos($sqlLog, $expect['update_excludes']) === false, "$name UPDATE excludes " . json_encode($expect['update_excludes']));
    }
    if (!empty($expect['success'])) {
        assert_true(strpos($html, 'erfolgreich ge') !== false, "$name success message");
    }

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

$article = "Line one\nLine two & friends";

run_case(
    'mode 13 edit form',
    array('mode' => '13', 'id' => '7'),
    array(),
    array('form' => true, 'stored_text' => $article)
);

run_case(
    'mode 13 save keeps paragraphs',
    array('mode' => '13', 'id' => '7'),
    array(
        'title' => 'Patch notes',
        'type' => '0',
        'img' => 'banner',
        'text' => $article,
    ),
    array(
        'success' => true,
        'stored_text' => $article,
        'update_contains' => "Line one\nLine two &amp; friends",
        'update_excludes' => '&lt;br',
    )
);

run_case(
    'mode 14 edit form',
    array('mode' => '14', 'id' => '7'),
    array(),
    array('form' => true, 'stored_text' => $article, 'stored_title' => 'Slider')
);

run_case(
    'mode 14 save keeps paragraphs',
    array('mode' => '14', 'id' => '7'),
    array(
        'title' => 'Slider',
        'img' => 'slide',
        'text' => $article,
    ),
    array(
        'success' => true,
        'stored_text' => $article,
        'update_contains' => "Line one\nLine two &amp; friends",
        'update_excludes' => '&lt;br',
    )
);

function run_roundtrip($name, $get, $postExtra)
{
    global $admin;

    $dir = sys_get_temp_dir() . '/newsedit_' . bin2hex(random_bytes(4));
    mkdir($dir . '/includes', 0700, true);
    $log = $dir . '/sql.log';
    file_put_contents($log, '');
    $stored = "Line one\nLine two &amp; friends";

    $config = <<<'PHP'
<?php
$login = true;
$session_right = 4;
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
    if (stripos(ltrim($sql), 'SELECT') === 0) {
        return 'row';
    }
    return 'updated';
}
function mysql_fetch_assoc($result) {
    if ($result !== 'row') {
        return false;
    }
    return array(
        'id' => '7',
        'title' => 'Patch notes',
        'text' => getenv('TEST_TEXT'),
        'image' => 'banner.png',
        'hot' => '0',
    );
}
function mysql_num_rows($result) {
    return 1;
}
PHP;
    file_put_contents($dir . '/includes/config.php', $config);
    file_put_contents($dir . '/includes/head.php', "<?php\n");
    file_put_contents($dir . '/includes/foot.php', "<?php\n");
    symlink($admin, $dir . '/admin.php');
    file_put_contents($dir . '/runner.php', <<<'PHP'
<?php
$_GET = json_decode(getenv('TEST_GET'), true);
$_POST = json_decode(getenv('TEST_POST'), true);
if (!is_array($_POST)) {
    $_POST = array();
}
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = empty($_POST) ? 'GET' : 'POST';
ob_start();
include 'admin.php';
file_put_contents(getenv('TEST_OUT'), ob_get_clean());
PHP
    );

    $run = function ($get, $post) use ($dir, $log, $stored) {
        file_put_contents($log, '');
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
        $env['TEST_TEXT'] = $stored;
        $cmd = 'php -d auto_prepend_file= ' . escapeshellarg($dir . '/runner.php');
        $descriptors = array(1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
        $proc = proc_open($cmd, $descriptors, $pipes, $dir, $env);
        stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        $html = file_get_contents($dir . '/out.html');
        return array($code, $html, file_get_contents($log), $stderr);
    };

    list($code, $html, $sqlLog, $stderr) = $run($get, array());
    assert_true($code === 0, "$name form exits 0 stderr=" . trim($stderr));
    $body = textarea_text($html);
    assert_true($body !== null && strpos($body, '<br') === false, "$name form body has no br tags: " . json_encode($body));
    $submitted = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $post = array_merge($postExtra, array('text' => $submitted));
    list($code, $html, $sqlLog, $stderr) = $run($get, $post);
    assert_true($code === 0, "$name save exits 0 stderr=" . trim($stderr));
    assert_true(strpos($sqlLog, "Line one\nLine two &amp; friends") !== false, "$name round-trip keeps paragraphs, log=" . json_encode($sqlLog));
    assert_true(strpos($sqlLog, '&lt;br') === false, "$name round-trip does not store br tags, log=" . json_encode($sqlLog));

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

run_roundtrip(
    'mode 13 round trip',
    array('mode' => '13', 'id' => '7'),
    array('title' => 'Patch notes', 'type' => '0', 'img' => 'banner')
);
run_roundtrip(
    'mode 14 round trip',
    array('mode' => '14', 'id' => '7'),
    array('title' => 'Slider', 'img' => 'slide')
);

if ($failures > 0) {
    fwrite(STDERR, "$failures assertion(s) failed\n");
    exit(1);
}

fwrite(STDOUT, "all news edit assertions passed\n");
exit(0);
