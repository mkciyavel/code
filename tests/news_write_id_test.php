<?php
/**
 * News delete, news edit, and cashshop edit must not treat the record id
 * or the hot flag as SQL. A non-numeric id used to delete or overwrite
 * every row.
 * Run: php tests/news_write_id_test.php
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

function run_case($name, $get, $post, $right = 4)
{
    global $admin;

    $dir = sys_get_temp_dir() . '/newswrite_' . bin2hex(random_bytes(4));
    mkdir($dir . '/includes', 0700, true);
    $log = $dir . '/sql.log';
    file_put_contents($log, '');

    file_put_contents($dir . '/includes/config.php', <<<'PHP'
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
    return str_replace(array("\\", "'"), array("\\\\", "\\'"), (string)$string);
}
function mysql_query($sql, $link = null) {
    file_put_contents(getenv('SQL_LOG'), $sql . "\n----\n", FILE_APPEND);
    if (!isset($GLOBALS['qs']) || !is_array($GLOBALS['qs'])) {
        $GLOBALS['qs'] = array();
        $GLOBALS['qi'] = array();
    }
    $key = 'q' . count($GLOBALS['qs']);
    $GLOBALS['qs'][$key] = true;
    $GLOBALS['qi'][$key] = 0;
    return $key;
}
function mysql_fetch_assoc($result) {
    if (empty($GLOBALS['qs'][$result])) {
        return false;
    }
    if ($GLOBALS['qi'][$result] > 0) {
        return false;
    }
    $GLOBALS['qi'][$result] = 1;
    return array(
        'id' => '5',
        'ID' => '5',
        'hot' => '1',
        'title' => 'Patch notes',
        'text' => 'Body text',
        'image' => 'banner.png',
        'itemID' => '100',
        'itemName' => 'Sword',
        'amount' => '2',
        'price' => '50',
    );
}
function mysql_num_rows($result) {
    return empty($GLOBALS['qs'][$result]) ? 0 : 1;
}
PHP
    );
    file_put_contents($dir . '/includes/head.php', "<?php\n");
    file_put_contents($dir . '/includes/foot.php', "<?php\n");
    if (!file_exists($dir . '/admin.php')) {
        symlink($admin, $dir . '/admin.php');
    }
    file_put_contents($dir . '/runner.php', <<<'PHP'
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
file_put_contents(getenv('TEST_OUT'), ob_get_clean());
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
    $env['TEST_RIGHT'] = (string)$right;

    $cmd = 'php -d auto_prepend_file= ' . escapeshellarg($dir . '/runner.php');
    $proc = proc_open($cmd, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, $dir, $env);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    $html = file_exists($dir . '/out.html') ? file_get_contents($dir . '/out.html') : '';
    $sqlLog = file_get_contents($log);

    assert_true($code === 0, "$name exits 0 (got $code) stderr=" . trim($stderr) . ' stdout=' . trim($stdout));

    $cleanup = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($cleanup as $file) {
        if ($file->isLink() || $file->isFile()) {
            unlink($file->getPathname());
        } else {
            rmdir($file->getPathname());
        }
    }
    rmdir($dir);

    return array($html, $sqlLog);
}

list($html, $sql) = run_case(
    'delete confirm does not delete yet',
    array('mode' => '4', 'id' => '5'),
    array()
);
assert_true(strpos($sql, 'DELETE') === false, 'opening the confirm form does not delete, sql=' . json_encode($sql));
assert_true(strpos($html, 'value="5"') !== false, 'confirm form keeps the numeric id');

list($html, $sql) = run_case(
    'news create form still opens',
    array('mode' => '1'),
    array()
);
assert_true(strpos($html, 'News erstellen') !== false, 'news create form still renders');
assert_true(strpos($sql, 'INSERT') === false, 'opening the create form does not insert, sql=' . json_encode($sql));

list($html, $sql) = run_case(
    'numeric news delete',
    array('mode' => '4'),
    array('id' => '5')
);
assert_true(strpos($sql, 'DELETE FROM news WHERE id = 5') !== false, 'delete targets only id 5, sql=' . json_encode($sql));
assert_true(strpos($sql, 'OR') === false, 'numeric delete has no OR clause');
assert_true(strpos($html, 'erfolgreich') !== false, 'numeric delete reports success');

list($html, $sql) = run_case(
    'quoted news delete',
    array('mode' => '4'),
    array('id' => "1' OR '1'='1")
);
assert_true(strpos($sql, 'DELETE') === false, 'quote in the delete id issues no DELETE, sql=' . json_encode($sql));
assert_true(strpos($html, 'erfolgreich') === false, 'injected delete is not reported as success');
assert_true(strpos($html, "1' OR '1'='1") === false, 'injected delete id is not echoed');

list($html, $sql) = run_case(
    'boolean news delete',
    array('mode' => '4'),
    array('id' => '1 OR 1=1')
);
assert_true(strpos($sql, 'DELETE') === false, 'OR delete id issues no DELETE, sql=' . json_encode($sql));
assert_true(strpos($html, 'konnte nicht') !== false, 'rejected delete explains the failure');

list($html, $sql) = run_case(
    'news delete requires admin right',
    array('mode' => '4'),
    array('id' => "1' OR '1'='1"),
    1
);
assert_true(strpos($sql, 'DELETE') === false, 'a lower-privilege session cannot delete news, sql=' . json_encode($sql));

list($html, $sql) = run_case(
    'numeric news save',
    array('mode' => '13', 'id' => '5'),
    array('title' => 'Patch notes', 'type' => '1', 'img' => 'banner', 'text' => 'Body text')
);
assert_true(strpos($sql, "UPDATE news SET hot = '1', title = 'Patch notes', image = 'banner.png', text = 'Body text' WHERE id = 5") !== false, 'news save keeps hot, title, and id, sql=' . json_encode($sql));
assert_true(strpos($html, 'erfolgreich') !== false, 'numeric news save reports success');

list($html, $sql) = run_case(
    'news id OR is rejected',
    array('mode' => '13', 'id' => '1 OR 1=1'),
    array('title' => 'Patch notes', 'type' => '1', 'img' => 'banner', 'text' => 'Body text')
);
assert_true(strpos($sql, 'UPDATE') === false, 'OR in the news id issues no UPDATE, sql=' . json_encode($sql));
assert_true(strpos($html, 'erfolgreich') === false, 'OR news id is not reported as saved');

list($html, $sql) = run_case(
    'hot flag cannot rewrite the UPDATE',
    array('mode' => '13', 'id' => '5'),
    array(
        'title' => 'Patch notes',
        'type' => "0', title='hacked', image='x.png', text='y' WHERE 1=1 -- ",
        'img' => 'banner',
        'text' => 'Body text',
    )
);
assert_true(strpos($sql, 'UPDATE') === false, 'a hot-flag payload issues no UPDATE, sql=' . json_encode($sql));
assert_true(strpos($sql, 'hacked') === false, 'hot-flag payload is not written, sql=' . json_encode($sql));
assert_true(strpos($html, 'erfolgreich') === false, 'hot-flag payload is not reported as saved');

list($html, $sql) = run_case(
    'news edit form still opens',
    array('mode' => '13', 'id' => '5'),
    array()
);
assert_true(strpos($sql, 'SELECT hot, title, text, image FROM news WHERE ID = 5') !== false, 'edit form loads one article, sql=' . json_encode($sql));
assert_true(strpos($html, 'News bearbeiten') !== false, 'edit form is shown for a numeric id');
assert_true(strpos($html, 'value="0"') !== false && strpos($html, 'value="1"') !== false, 'hot flag choices are still 0 and 1');

list($html, $sql) = run_case(
    'slider id OR is rejected',
    array('mode' => '14', 'id' => '5 OR 1=1'),
    array('title' => 'Slider', 'img' => 'slide', 'text' => 'Text')
);
assert_true(strpos($sql, 'UPDATE') === false, 'OR in the slider id issues no UPDATE, sql=' . json_encode($sql));
assert_true(strpos($html, 'erfolgreich') === false, 'slider injection is not reported as saved');

list($html, $sql) = run_case(
    'numeric slider save',
    array('mode' => '14', 'id' => '8'),
    array('title' => 'Slider', 'img' => 'slide', 'text' => 'Text')
);
assert_true(strpos($sql, "UPDATE hottest_news SET title = 'Slider', image = 'slide.png', text = 'Text' WHERE id = 8") !== false, 'slider save targets id 8, sql=' . json_encode($sql));

list($html, $sql) = run_case(
    'cashshop id quote is rejected',
    array('mode' => '10', 'action' => '1', 'id' => "4' OR '1'='1"),
    array('itemID' => '100', 'itemName' => 'Sword', 'itemAmount' => '2', 'itemText' => 'Sharp', 'itemPrice' => '50')
);
assert_true(strpos($sql, 'UPDATE') === false, 'quote in the cashshop id issues no UPDATE, sql=' . json_encode($sql));
assert_true(strpos($html, 'erfolgreich') === false, 'cashshop injection is not reported as saved');

list($html, $sql) = run_case(
    'numeric cashshop save',
    array('mode' => '10', 'action' => '1', 'id' => '4'),
    array('itemID' => '100', 'itemName' => 'Sword', 'itemAmount' => '2', 'itemText' => 'Sharp', 'itemPrice' => '50')
);
assert_true(strpos($sql, "WHERE ID = 4") !== false, 'cashshop save targets id 4, sql=' . json_encode($sql));
assert_true(strpos($sql, "ItemName = 'Sword'") !== false, 'cashshop save keeps the item name, sql=' . json_encode($sql));
assert_true(strpos($html, 'erfolgreich') !== false, 'numeric cashshop save reports success');

if ($failures > 0) {
    fwrite(STDERR, "$failures assertion(s) failed\n");
    exit(1);
}
fwrite(STDOUT, "all news write id assertions passed\n");
exit(0);
