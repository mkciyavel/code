<?php
/**
 * Item mail (mode 11) is shown by the game client as plain text.
 * htmlentities() turned German messages into entities, and MySQL
 * backslash escapes broke the SQL Server string.
 * Run: php tests/item_send_test.php
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

function run_case($name, $post, $expect)
{
    global $admin;

    $dir = sys_get_temp_dir() . '/itemsend_' . bin2hex(random_bytes(4));
    mkdir($dir . '/includes', 0700, true);
    $log = $dir . '/odbc.log';
    file_put_contents($log, '');

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
    return addslashes((string)$string);
}
function mysql_query($sql, $link = null) {
    return true;
}
function mysql_fetch_assoc($result) {
    return false;
}
function mysql_num_rows($result) {
    return 0;
}
function odbc_exec($conn, $sql) {
    file_put_contents(getenv('SQL_LOG'), $sql . "\n----\n", FILE_APPEND);
    if (getenv('EXEC_FAIL') === '1' && stripos($sql, 'TEventItemGive') !== false) {
        return false;
    }
    if (getenv('CHAR_MISSING') === '1' && stripos($sql, 'TCHARTABLE') !== false) {
        return 'missing';
    }
    return 'q';
}
function odbc_fetch_array($result) {
    return false;
}
function odbc_num_rows($result) {
    if ($result === false || $result === 'missing') {
        return 0;
    }
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
$_SERVER['REQUEST_METHOD'] = 'POST';
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
    $env['TEST_GET'] = json_encode(array('mode' => '11'));
    $env['TEST_POST'] = json_encode($post);
    $env['EXEC_FAIL'] = !empty($expect['exec_fail']) ? '1' : '0';
    $env['CHAR_MISSING'] = !empty($expect['char_missing']) ? '1' : '0';

    $cmd = 'php -d auto_prepend_file= ' . escapeshellarg($dir . '/runner.php');
    $descriptors = array(1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
    $proc = proc_open($cmd, $descriptors, $pipes, $dir, $env);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    $html = file_exists($dir . '/out.html') ? file_get_contents($dir . '/out.html') : '';
    $sqlLog = file_get_contents($log);

    assert_true($code === 0, "$name exits 0 (got $code) stderr=" . trim($stderr) . ' stdout=' . trim($stdout));

    if (!empty($expect['exec_contains'])) {
        assert_true(stripos($sqlLog, 'TEventItemGive') !== false, "$name calls TEventItemGive");
        assert_true(strpos($sqlLog, $expect['exec_contains']) !== false, "$name EXEC contains " . json_encode($expect['exec_contains']) . ' log=' . json_encode($sqlLog));
    }
    if (!empty($expect['exec_excludes'])) {
        assert_true(strpos($sqlLog, $expect['exec_excludes']) === false, "$name EXEC excludes " . json_encode($expect['exec_excludes']) . ' log=' . json_encode($sqlLog));
    }
    if (!empty($expect['no_exec'])) {
        assert_true(stripos($sqlLog, 'TEventItemGive') === false, "$name does not call TEventItemGive, log=" . json_encode($sqlLog));
    }
    if (!empty($expect['success'])) {
        assert_true(strpos($html, 'erfolgreich verschickt') !== false, "$name success message");
    }
    if (!empty($expect['no_success'])) {
        assert_true(strpos($html, 'erfolgreich verschickt') === false, "$name does not claim success html=" . json_encode($html));
    }
    if (!empty($expect['error_contains'])) {
        assert_true(strpos($html, $expect['error_contains']) !== false, "$name shows error " . json_encode($expect['error_contains']) . ' html=' . json_encode($html));
    }

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

$base = array(
    'username' => 'Gandalf',
    'itemID' => '4201',
    'amount' => '3',
    'title' => 'Entschädigung',
    'text' => 'Entschuldigung für den Ausfall',
);

run_case(
    'german mail stays plain text',
    $base,
    array(
        'success' => true,
        'exec_contains' => "@szMessage = 'Entschuldigung für den Ausfall', @szTitle = 'Entschädigung'",
        'exec_excludes' => '&uuml;',
    )
);

run_case(
    'apostrophe is escaped for SQL Server',
    array_merge($base, array(
        'username' => "Da'Rosh",
        'title' => "Wir haben's",
        'text' => "Bitte prüf's nochmal",
    )),
    array(
        'success' => true,
        'exec_contains' => "@szName = 'Da''Rosh'",
        'exec_excludes' => '&#039;',
    )
);

run_case(
    'apostrophe message is doubled not backslashed',
    array_merge($base, array('text' => "Bitte prüf's nochmal")),
    array(
        'success' => true,
        'exec_contains' => "@szMessage = 'Bitte prüf''s nochmal'",
        'exec_excludes' => "\\'",
    )
);

run_case(
    'failed give is not reported as success',
    $base,
    array(
        'exec_fail' => true,
        'no_success' => true,
        'error_contains' => 'konnte nicht verschickt',
    )
);

run_case(
    'missing character does not give an item',
    $base,
    array(
        'char_missing' => true,
        'no_exec' => true,
        'no_success' => true,
        'error_contains' => 'existiert nicht',
    )
);

run_case(
    'non numeric item id is rejected',
    array_merge($base, array('itemID' => "1' OR '1'='1")),
    array(
        'no_exec' => true,
        'no_success' => true,
    )
);

if ($failures > 0) {
    fwrite(STDERR, "$failures assertion(s) failed\n");
    exit(1);
}

fwrite(STDOUT, "all item send assertions passed\n");
exit(0);
