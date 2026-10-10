<?php
/**
 * Character money edit (mode 8 action 10) must not write blank amounts.
 * Run: php tests/character_money_test.php
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

    $dir = sys_get_temp_dir() . '/moneytest_' . bin2hex(random_bytes(4));
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
    if (stripos($sql, 'SELECT dwGold') !== false) {
        return getenv('TEST_NO_CHAR') === '1' ? 'empty' : 'money';
    }
    if (stripos($sql, 'UPDATE TGAME_GSP.dbo.TCHARTABLE SET dwGold') !== false) {
        return getenv('TEST_UPDATE_FAIL') === '1' ? false : 'updated';
    }
    return 'other';
}
function odbc_num_rows($result) {
    return 0;
}
function odbc_fetch_array($result) {
    if ($result !== 'money') {
        return false;
    }
    return array(
        'dwGold' => getenv('TEST_GOLD'),
        'dwSilver' => getenv('TEST_SILVER'),
        'dwCooper' => getenv('TEST_COOPER'),
    );
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
    $env['TEST_POST'] = json_encode($post);
    $env['TEST_RIGHT'] = isset($expect['right']) ? (string)$expect['right'] : '4';
    $env['TEST_NO_CHAR'] = !empty($expect['no_char']) ? '1' : '0';
    $env['TEST_UPDATE_FAIL'] = !empty($expect['update_fail']) ? '1' : '0';
    $env['TEST_GOLD'] = isset($expect['gold']) ? (string)$expect['gold'] : '1000';
    $env['TEST_SILVER'] = isset($expect['silver']) ? (string)$expect['silver'] : '50';
    $env['TEST_COOPER'] = isset($expect['cooper']) ? (string)$expect['cooper'] : '7';

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
        assert_true(strpos($sqlLog, 'UPDATE TGAME_GSP.dbo.TCHARTABLE SET dwGold') !== false, "$name issues UPDATE");
        assert_true(strpos($sqlLog, $expect['update_contains']) !== false, "$name UPDATE contains " . $expect['update_contains']);
    } else {
        assert_true(strpos($sqlLog, 'UPDATE TGAME_GSP.dbo.TCHARTABLE SET dwGold') === false, "$name does not UPDATE money");
    }
    if (!empty($expect['values'])) {
        foreach ($expect['values'] as $needle) {
            assert_true(strpos($html, $needle) !== false, "$name form contains $needle");
        }
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

$get = array('mode' => '8', 'action' => '10', 'cid' => '42');

run_case(
    'form prefills current money',
    $get,
    array(),
    array(
        'success' => false,
        'gold' => '1000',
        'silver' => '50',
        'cooper' => '7',
        'values' => array('value="1000"', 'value="50"', 'value="7"'),
    )
);

run_case(
    'blank silver and bronze are not written',
    $get,
    array('newgold' => '5000', 'newsilver' => '', 'newcooper' => ''),
    array(
        'success' => false,
        'error' => 'ganze Zahl',
        'gold' => '1000',
        'silver' => '50',
        'cooper' => '7',
    )
);

run_case(
    'whitespace-only amount is rejected',
    $get,
    array('newgold' => '5000', 'newsilver' => '   ', 'newcooper' => '7'),
    array(
        'success' => false,
        'error' => 'ganze Zahl',
    )
);

run_case(
    'explicit amounts are saved',
    $get,
    array('newgold' => '5000', 'newsilver' => '50', 'newcooper' => '0'),
    array(
        'success' => true,
        'update_contains' => "dwGold = '5000', dwSilver = '50', dwCooper = '0'",
    )
);

run_case(
    'non-numeric amount is rejected',
    $get,
    array('newgold' => '12a', 'newsilver' => '1', 'newcooper' => '1'),
    array(
        'success' => false,
        'error' => 'ganze Zahl',
    )
);

run_case(
    'overflow amount is rejected',
    $get,
    array('newgold' => '2147483648', 'newsilver' => '1', 'newcooper' => '1'),
    array(
        'success' => false,
        'error' => 'ganze Zahl',
    )
);

run_case(
    'missing character does not update',
    $get,
    array('newgold' => '1', 'newsilver' => '1', 'newcooper' => '1'),
    array(
        'success' => false,
        'no_char' => true,
        'error' => 'Der Charakter existiert nicht!',
    )
);

run_case(
    'failed update is not reported as success',
    $get,
    array('newgold' => '1', 'newsilver' => '2', 'newcooper' => '3'),
    array(
        'success' => false,
        'update_fail' => true,
        'error' => 'Das Geld konnte nicht',
        'update_contains' => "dwGold = '1', dwSilver = '2', dwCooper = '3'",
    )
);

run_case(
    'low privilege cannot change money',
    $get,
    array('newgold' => '1', 'newsilver' => '2', 'newcooper' => '3'),
    array(
        'success' => false,
        'right' => '1',
    )
);

if ($failures > 0) {
    fwrite(STDERR, "$failures assertion(s) failed\n");
    exit(1);
}

fwrite(STDOUT, "all character money cases passed\n");
exit(0);
