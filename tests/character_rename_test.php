<?php
/**
 * Character rename (mode 8 action 11) must not wipe or rewrite names.
 * Run: php tests/character_rename_test.php
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

    $dir = sys_get_temp_dir() . '/renametest_' . bin2hex(random_bytes(4));
    mkdir($dir . '/includes', 0700, true);
    $log = $dir . '/odbc.log';
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
function odbc_exec($con, $sql) {
    $log = getenv('ODBC_LOG');
    file_put_contents($log, $sql . "\n----\n", FILE_APPEND);
    if (stripos($sql, 'SELECT szName FROM TGAME_GSP.dbo.TCHARTABLE') !== false) {
        return getenv('TEST_LOOKUP_FAIL') === '1' ? false : 'current';
    }
    if (stripos($sql, 'SELECT dwCharID FROM TGAME_GSP.dbo.TCHARTABLE WHERE szName') !== false) {
        return getenv('TEST_DUP_FAIL') === '1' ? false : 'dup';
    }
    if (stripos($sql, 'UPDATE TGAME_GSP.dbo.TCHARTABLE SET szName') !== false) {
        return getenv('TEST_UPDATE_FAIL') === '1' ? false : 'updated';
    }
    return 'other';
}
function odbc_num_rows($result) {
    return 0;
}
function odbc_fetch_array($result) {
    if ($result === 'current') {
        if (getenv('TEST_NO_CHAR') === '1') {
            return false;
        }
        return array('szName' => getenv('TEST_NAME'));
    }
    if ($result === 'dup' && getenv('TEST_DUP') === '1') {
        return array('dwCharID' => '99');
    }
    return false;
}
PHP;
    file_put_contents($dir . '/includes/config.php', $config);
    file_put_contents($dir . '/includes/head.php', "<?php\n");
    file_put_contents($dir . '/includes/foot.php', "<?php\n");
    symlink($admin, $dir . '/admin.php');

    $runner = $dir . '/runner.php';
    file_put_contents($runner, <<<'PHP'
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
    $env['ODBC_LOG'] = $log;
    $env['TEST_OUT'] = $dir . '/out.html';
    $env['TEST_GET'] = json_encode($get);
    $env['TEST_POST'] = json_encode($post === null ? new stdClass() : $post);
    $env['TEST_RIGHT'] = isset($expect['right']) ? (string)$expect['right'] : '4';
    $env['TEST_NO_CHAR'] = !empty($expect['no_char']) ? '1' : '0';
    $env['TEST_LOOKUP_FAIL'] = !empty($expect['lookup_fail']) ? '1' : '0';
    $env['TEST_DUP'] = !empty($expect['dup']) ? '1' : '0';
    $env['TEST_DUP_FAIL'] = !empty($expect['dup_fail']) ? '1' : '0';
    $env['TEST_UPDATE_FAIL'] = !empty($expect['update_fail']) ? '1' : '0';
    $env['TEST_NAME'] = isset($expect['current']) ? (string)$expect['current'] : 'Aldor';

    $cmd = 'php -d display_errors=1 -d auto_prepend_file= ' . escapeshellarg($runner);
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

    assert_true($code === 0, "$name exits 0 (got $code) stderr=" . trim($stderr));
    if (!empty($expect['success'])) {
        assert_true(strpos($html, 'erfolgreich ge') !== false, "$name success message");
    } else {
        assert_true(strpos($html, 'erfolgreich ge') === false, "$name does not claim success");
    }
    if (isset($expect['error'])) {
        assert_true(strpos($html, $expect['error']) !== false, "$name shows error {$expect['error']}");
    }
    if (!empty($expect['update_contains'])) {
        assert_true(strpos($sqlLog, 'UPDATE TGAME_GSP.dbo.TCHARTABLE SET szName') !== false, "$name issues UPDATE");
        assert_true(strpos($sqlLog, $expect['update_contains']) !== false, "$name UPDATE contains " . $expect['update_contains']);
    } else {
        assert_true(strpos($sqlLog, 'UPDATE TGAME_GSP.dbo.TCHARTABLE SET szName') === false, "$name does not UPDATE the name");
    }
    if (!empty($expect['html'])) {
        foreach ($expect['html'] as $needle) {
            assert_true(strpos($html, $needle) !== false, "$name form contains $needle");
        }
    }
    if (!empty($expect['sql_contains'])) {
        assert_true(strpos($sqlLog, $expect['sql_contains']) !== false, "$name SQL contains " . $expect['sql_contains']);
    }
    if (!empty($expect['sql_absent'])) {
        assert_true(strpos($sqlLog, $expect['sql_absent']) === false, "$name SQL does not contain " . $expect['sql_absent']);
    }

    @unlink($dir . '/admin.php');
    @unlink($dir . '/runner.php');
    @unlink($dir . '/includes/config.php');
    @unlink($dir . '/includes/head.php');
    @unlink($dir . '/includes/foot.php');
    @unlink($log);
    @unlink($dir . '/out.html');
    @rmdir($dir . '/includes');
    @rmdir($dir);

    if ($stdout !== '') {
        fwrite(STDOUT, $stdout);
    }
}

$get = array('mode' => '8', 'action' => '11', 'cid' => '42');

run_case(
    'form prefills the current name',
    $get,
    array(),
    array(
        'success' => false,
        'current' => "Da'Rosh",
        'html' => array('value="Da&#039;Rosh"'),
    )
);

run_case(
    'blank name is not written',
    $get,
    array('newname' => ''),
    array(
        'success' => false,
        'error' => 'Ein leeres Feld',
    )
);

run_case(
    'whitespace-only name is rejected',
    $get,
    array('newname' => '   '),
    array(
        'success' => false,
        'error' => 'Ein leeres Feld',
    )
);

run_case(
    'apostrophe is doubled for SQL Server',
    $get,
    array('newname' => "Da'Rosh"),
    array(
        'success' => true,
        'update_contains' => "szName = 'Da''Rosh' WHERE dwCharID = '42'",
    )
);

run_case(
    'a quote cannot rewrite the WHERE clause',
    $get,
    array('newname' => "a' WHERE dwCharID = dwCharID --"),
    array(
        'success' => true,
        'update_contains' => "szName = 'a'' WHERE dwCharID = dwCharID --' WHERE dwCharID = '42'",
        'sql_absent' => "SET szName = 'a' WHERE dwCharID = dwCharID --'",
    )
);

run_case(
    'duplicate name is rejected',
    $get,
    array('newname' => 'Aldor'),
    array(
        'success' => false,
        'dup' => true,
        'error' => 'existiert bereits',
        'sql_contains' => "szName = 'Aldor' AND dwCharID <> '42'",
    )
);

run_case(
    'missing character does not update',
    $get,
    array('newname' => 'Neu'),
    array(
        'success' => false,
        'no_char' => true,
        'error' => 'Der Charakter existiert nicht!',
    )
);

run_case(
    'failed lookup is not reported as success',
    $get,
    array('newname' => 'Neu'),
    array(
        'success' => false,
        'lookup_fail' => true,
        'error' => 'konnte nicht',
    )
);

run_case(
    'failed update is not reported as success',
    $get,
    array('newname' => 'Neu'),
    array(
        'success' => false,
        'update_fail' => true,
        'error' => 'konnte nicht',
        'update_contains' => "szName = 'Neu' WHERE dwCharID = '42'",
    )
);

run_case(
    'low privilege cannot rename',
    $get,
    array('newname' => 'Neu'),
    array(
        'success' => false,
        'right' => '1',
    )
);

run_case(
    'invalid character id does not update',
    array('mode' => '8', 'action' => '11', 'cid' => "42' OR '1'='1"),
    array('newname' => 'Neu'),
    array(
        'success' => false,
        'error' => 'Der Charakter existiert nicht!',
    )
);

if ($failures > 0) {
    fwrite(STDERR, "$failures assertion(s) failed\n");
    exit(1);
}

fwrite(STDOUT, "all character rename assertions passed\n");
exit(0);
