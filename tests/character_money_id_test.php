<?php
/**
 * Character money edit must only update the one numeric character id.
 * Run: php tests/character_money_id_test.php
 *
 * A cid of 1' OR 1=1 -- used to survive mysql_real_escape_string as
 * 1\' OR 1=1 --. SQL Server treats the backslash as data, so the quote
 * ends the string, the rest of the line is commented out, and
 * WHERE dwCharID = '1\' OR 1=1 updates every character.
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

    $dir = sys_get_temp_dir() . '/moneyid_' . bin2hex(random_bytes(4));
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
    return addslashes((string)$string);
}
function odbc_exec($con, $sql) {
    $log = getenv('ODBC_LOG');
    file_put_contents($log, $sql . "\n----\n", FILE_APPEND);
    return 'updated';
}
function odbc_num_rows($result) {
    return 0;
}
function odbc_fetch_array($result) {
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
    $env['ODBC_LOG'] = $log;
    $env['TEST_OUT'] = $dir . '/out.html';
    $env['TEST_GET'] = json_encode($get);
    $env['TEST_POST'] = json_encode($post);
    $env['TEST_RIGHT'] = isset($expect['right']) ? (string)$expect['right'] : '4';

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
    if (!empty($expect['success'])) {
        assert_true(strpos($html, 'erfolgreich ge') !== false, "$name success message");
    } else {
        assert_true(strpos($html, 'erfolgreich ge') === false, "$name does not claim success");
    }
    if (!empty($expect['update'])) {
        foreach ((array)$expect['update'] as $needle) {
            assert_true(strpos($sqlLog, $needle) !== false, "$name SQL contains " . $needle . " log=" . $sqlLog);
        }
        assert_true(substr_count($sqlLog, 'UPDATE ') === 1, "$name issues one UPDATE");
    } else {
        assert_true(strpos($sqlLog, 'UPDATE ') === false, "$name does not UPDATE. log=" . $sqlLog);
        assert_true(strpos($sqlLog, 'DELETE ') === false, "$name does not DELETE. log=" . $sqlLog);
    }
    if (!empty($expect['html'])) {
        assert_true(strpos($html, $expect['html']) !== false, "$name html contains " . $expect['html']);
    }
    if (!empty($expect['html_absent'])) {
        assert_true(strpos($html, $expect['html_absent']) === false, "$name html omits " . $expect['html_absent']);
    }

    if (is_dir($dir)) {
        @unlink($dir . '/admin.php');
        @unlink($dir . '/runner.php');
        @unlink($dir . '/out.html');
        @unlink($dir . '/odbc.log');
        @unlink($dir . '/includes/config.php');
        @unlink($dir . '/includes/head.php');
        @unlink($dir . '/includes/foot.php');
        @rmdir($dir . '/includes');
        @rmdir($dir);
    }
}

$amounts = array('newgold' => '1000', 'newsilver' => '50', 'newcooper' => '7');

run_case(
    'numeric id updates only that character',
    array('mode' => '8', 'action' => '10', 'cid' => '7'),
    $amounts,
    array(
        'success' => true,
        'update' => array(
            "SET dwGold = '1000', dwSilver = '50', dwCooper = '7'",
            "WHERE dwCharID = '7'",
        ),
        'html' => 'admin_cp.php?mode=8&cid=7',
    )
);

run_case(
    'quote in cid does not update every character',
    array('mode' => '8', 'action' => '10', 'cid' => "1' OR 1=1 --"),
    $amounts,
    array(
        'success' => false,
        'html' => 'Der Charakter existiert nicht!',
        'html_absent' => 'OR 1=1',
    )
);

run_case(
    'batched delete cid is not executed',
    array('mode' => '8', 'action' => '10', 'cid' => "1'; DELETE FROM TGAME_GSP.dbo.TCHARTABLE; --"),
    $amounts,
    array(
        'success' => false,
        'html' => 'Der Charakter existiert nicht!',
    )
);

run_case(
    'cid of zero is rejected',
    array('mode' => '8', 'action' => '10', 'cid' => '0'),
    $amounts,
    array('success' => false, 'html' => 'Der Charakter existiert nicht!')
);

run_case(
    'non-numeric cid is rejected',
    array('mode' => '8', 'action' => '10', 'cid' => '7abc'),
    $amounts,
    array('success' => false)
);

run_case(
    'low privilege cannot change money',
    array('mode' => '8', 'action' => '10', 'cid' => '7'),
    $amounts,
    array('success' => false, 'right' => '1')
);

run_case(
    'money form renders for a real character',
    array('mode' => '8', 'action' => '10', 'cid' => '7'),
    array(),
    array(
        'success' => false,
        'html' => 'action=10&cid=7',
    )
);

if ($failures > 0) {
    fwrite(STDERR, "$failures assertion(s) failed\n");
    exit(1);
}

fwrite(STDOUT, "all character money id checks passed\n");
exit(0);
