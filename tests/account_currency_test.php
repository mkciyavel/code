<?php
/**
 * Account currency edit (mode 7 actions 2 and 3) must not write blank amounts.
 * Run: php tests/account_currency_test.php
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

    $dir = sys_get_temp_dir() . '/currencytest_' . bin2hex(random_bytes(4));
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
    if (stripos($sql, 'SELECT dwCash') !== false) {
        return getenv('TEST_LOOKUP_FAIL') === '1' ? false : 'cash';
    }
    if (stripos($sql, 'UPDATE TGLOBAL_GSP.dbo.TCASHTESTTABLE') !== false
        || stripos($sql, 'INSERT INTO TGLOBAL_GSP.dbo.TCASHTESTTABLE') !== false) {
        return getenv('TEST_WRITE_FAIL') === '1' ? false : 'written';
    }
    return 'other';
}
function odbc_num_rows($result) {
    if (getenv('TEST_NO_ROW') === '1') {
        return 0;
    }
    return 1;
}
function odbc_fetch_array($result) {
    if ($result !== 'cash' || getenv('TEST_NO_ROW') === '1') {
        return false;
    }
    return array(
        'dwCash' => getenv('TEST_CASH'),
        'dwBonus' => getenv('TEST_BONUS'),
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
    $env['TEST_NO_ROW'] = !empty($expect['no_row']) ? '1' : '0';
    $env['TEST_LOOKUP_FAIL'] = !empty($expect['lookup_fail']) ? '1' : '0';
    $env['TEST_WRITE_FAIL'] = !empty($expect['write_fail']) ? '1' : '0';
    $env['TEST_CASH'] = isset($expect['cash']) ? (string)$expect['cash'] : '2500';
    $env['TEST_BONUS'] = isset($expect['bonus']) ? (string)$expect['bonus'] : '80';

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
    if (!empty($expect['write_contains'])) {
        assert_true(strpos($sqlLog, $expect['write_contains']) !== false, "$name writes " . $expect['write_contains']);
    }
    if (!empty($expect['write_absent'])) {
        assert_true(strpos($sqlLog, $expect['write_absent']) === false, "$name does not write " . $expect['write_absent']);
    }
    if (!empty($expect['no_write'])) {
        assert_true(strpos($sqlLog, 'UPDATE TGLOBAL_GSP.dbo.TCASHTESTTABLE') === false, "$name does not UPDATE currency");
        assert_true(strpos($sqlLog, 'INSERT INTO TGLOBAL_GSP.dbo.TCASHTESTTABLE') === false, "$name does not INSERT currency");
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

$cashGet = array('mode' => '7', 'action' => '2', 'id' => '42');
$bonusGet = array('mode' => '7', 'action' => '3', 'id' => '42');

run_case(
    'cash form prefills current balance',
    $cashGet,
    array(),
    array(
        'success' => false,
        'cash' => '2500',
        'values' => array('value="2500"'),
        'no_write' => true,
    )
);

run_case(
    'blank cash is not written',
    $cashGet,
    array('newms' => ''),
    array(
        'success' => false,
        'error' => 'ganze Zahl',
        'no_write' => true,
    )
);

run_case(
    'whitespace cash is rejected',
    $cashGet,
    array('newms' => '   '),
    array(
        'success' => false,
        'error' => 'ganze Zahl',
        'no_write' => true,
    )
);

run_case(
    'explicit cash is saved',
    $cashGet,
    array('newms' => '6000'),
    array(
        'success' => true,
        'write_contains' => "SET dwCash = '6000'",
    )
);

run_case(
    'explicit zero cash is saved',
    $cashGet,
    array('newms' => '0'),
    array(
        'success' => true,
        'write_contains' => "SET dwCash = '0'",
    )
);

run_case(
    'non-numeric cash is rejected',
    $cashGet,
    array('newms' => "100' OR '1'='1"),
    array(
        'success' => false,
        'error' => 'ganze Zahl',
        'no_write' => true,
    )
);

run_case(
    'overflow cash is rejected',
    $cashGet,
    array('newms' => '2147483648'),
    array(
        'success' => false,
        'error' => 'ganze Zahl',
        'no_write' => true,
    )
);

run_case(
    'missing cash row inserts the typed amount',
    $cashGet,
    array('newms' => '15'),
    array(
        'success' => true,
        'no_row' => true,
        'write_contains' => "VALUES('42', '15', '0')",
    )
);

run_case(
    'failed cash update is not reported as success',
    $cashGet,
    array('newms' => '15'),
    array(
        'success' => false,
        'write_fail' => true,
        'error' => 'konnten nicht',
        'write_contains' => "SET dwCash = '15'",
    )
);

run_case(
    'failed cash lookup does not write',
    $cashGet,
    array('newms' => '15'),
    array(
        'success' => false,
        'lookup_fail' => true,
        'error' => 'konnten nicht',
        'no_write' => true,
    )
);

run_case(
    'invalid account id does not write cash',
    array('mode' => '7', 'action' => '2', 'id' => "42' OR '1'='1"),
    array('newms' => '15'),
    array(
        'success' => false,
        'error' => 'Der Account existiert nicht!',
        'no_write' => true,
    )
);

run_case(
    'low privilege cannot change cash',
    $cashGet,
    array('newms' => '15'),
    array(
        'success' => false,
        'right' => '1',
        'no_write' => true,
    )
);

run_case(
    'bonus form prefills current balance',
    $bonusGet,
    array(),
    array(
        'success' => false,
        'bonus' => '80',
        'values' => array('value="80"'),
        'no_write' => true,
    )
);

run_case(
    'blank bonus is not written',
    $bonusGet,
    array('newbp' => ''),
    array(
        'success' => false,
        'error' => 'ganze Zahl',
        'no_write' => true,
    )
);

run_case(
    'explicit bonus is saved without changing cash',
    $bonusGet,
    array('newbp' => '40'),
    array(
        'success' => true,
        'write_contains' => "SET dwBonus = '40'",
        'write_absent' => "SET dwCash",
    )
);

run_case(
    'missing bonus row inserts without wiping a cash column on an existing pattern',
    $bonusGet,
    array('newbp' => '9'),
    array(
        'success' => true,
        'no_row' => true,
        'write_contains' => "VALUES('42', '0', '9')",
    )
);

if ($failures > 0) {
    fwrite(STDERR, "$failures assertion(s) failed\n");
    exit(1);
}

fwrite(STDOUT, "all account currency cases passed\n");
exit(0);
