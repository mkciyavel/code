<?php
/**
 * Exercises admin account creation (mode 5) with stubbed ODBC.
 * Run: php tests/account_create_test.php
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

function run_case($name, $get, $post, $configExtra, $expect)
{
    global $admin;

    $dir = sys_get_temp_dir() . '/accttest_' . bin2hex(random_bytes(4));
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
function odbc_exec($con, $sql) {
    $log = getenv('ODBC_LOG');
    file_put_contents($log, $sql . "\n----\n", FILE_APPEND);
    if (getenv('TEST_EXEC_FAIL') === '1') {
        return false;
    }
    if (stripos($sql, 'SELECT dwUserID') !== false) {
        return getenv('TEST_USER_EXISTS') === '1' ? 'exists' : 'empty';
    }
    if (stripos($sql, 'MAX(dwUserID)') !== false) {
        return getenv('TEST_MAX_FAIL') === '1' ? false : 'max';
    }
    if (stripos($sql, 'INSERT INTO') !== false && getenv('TEST_INSERT_FAIL') === '1') {
        return false;
    }
    return 'ok';
}
function odbc_num_rows($result) {
    if ($result === 'exists') return 1;
    if ($result === 'empty') return 0;
    return -1;
}
function odbc_fetch_array($result) {
    if ($result === 'max') {
        $id = getenv('TEST_MAX_ID');
        return array('Result' => ($id === false || $id === '') ? null : $id);
    }
    return false;
}
PHP;
    $config .= "\n" . $configExtra;
    file_put_contents($dir . '/includes/config.php', $config);
    file_put_contents($dir . '/includes/head.php', "<?php\n");
    file_put_contents($dir . '/includes/foot.php', "<?php\n");
    symlink($admin, $dir . '/admin.php');

    $runner = $dir . '/runner.php';
    file_put_contents($runner, <<<'PHP'
<?php
$_GET = json_decode(getenv('TEST_GET'), true);
$_POST = json_decode(getenv('TEST_POST'), true);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'POST';
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
    $env['TEST_USER_EXISTS'] = !empty($expect['exists']) ? '1' : '0';
    $env['TEST_EXEC_FAIL'] = !empty($expect['exec_fail']) ? '1' : '0';
    $env['TEST_MAX_FAIL'] = !empty($expect['max_fail']) ? '1' : '0';
    $env['TEST_INSERT_FAIL'] = !empty($expect['insert_fail']) ? '1' : '0';
    $env['TEST_MAX_ID'] = array_key_exists('max_id', $expect) ? (string)$expect['max_id'] : '10';

    // Skip the dev auto_prepend, which defines odbc_* and redirects includes.
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
        assert_true(strpos($html, 'erfolgreich erstellt') !== false, "$name success message");
    } else {
        assert_true(strpos($html, 'erfolgreich erstellt') === false, "$name does not claim success");
    }
    if (isset($expect['error'])) {
        assert_true(strpos($html, $expect['error']) !== false, "$name shows error {$expect['error']}");
    }
    if (!empty($expect['shows_form'])) {
        assert_true(strpos($html, 'name="email"') !== false, "$name renders email field");
    }
    if (isset($expect['insert_contains'])) {
        assert_true(strpos($sqlLog, 'INSERT INTO') !== false, "$name issues INSERT");
        foreach ($expect['insert_contains'] as $needle) {
            assert_true(strpos($sqlLog, $needle) !== false, "$name INSERT contains " . $needle);
        }
    } else {
        assert_true(strpos($sqlLog, 'INSERT INTO') === false, "$name does not INSERT");
    }
    if (!empty($expect['no_odbc'])) {
        assert_true(trim($sqlLog) === '', "$name does not touch the database");
    }

    // cleanup
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

run_case(
    'create new account',
    array('mode' => '5'),
    array('username' => 'NewPlayer', 'password' => 'secret1', 'password2' => 'secret1', 'email' => 'a@b.c'),
    '',
    array(
        'success' => true,
        'max_id' => '10',
        'insert_contains' => array("VALUES(11, 'NewPlayer', 'secret1', '1', 'a@b.c')"),
    )
);

run_case(
    'empty account table uses id 1',
    array('mode' => '5'),
    array('username' => 'First', 'password' => 'secret1', 'password2' => 'secret1', 'email' => ''),
    '',
    array(
        'success' => true,
        'max_id' => '',
        'insert_contains' => array("VALUES(1, 'First', 'secret1', '1', '')"),
    )
);

run_case(
    'password mismatch',
    array('mode' => '5'),
    array('username' => 'NewPlayer', 'password' => 'secret1', 'password2' => 'other', 'email' => 'a@b.c'),
    '',
    array(
        'success' => false,
        'error' => 'Das Passwort war nicht korrekt!',
        'no_odbc' => true,
        'shows_form' => true,
    )
);

run_case(
    'empty username',
    array('mode' => '5'),
    array('username' => '   ', 'password' => 'secret1', 'password2' => 'secret1'),
    '',
    array(
        'success' => false,
        'error' => 'Der Accountname ist erforderlich!',
        'no_odbc' => true,
    )
);

run_case(
    'duplicate name',
    array('mode' => '5'),
    array('username' => 'Taken', 'password' => 'secret1', 'password2' => 'secret1', 'email' => 'a@b.c'),
    '',
    array(
        'success' => false,
        'exists' => true,
        'error' => 'Der Accountname existiert bereits!',
    )
);

run_case(
    'quote in username is escaped',
    array('mode' => '5'),
    array('username' => "O'Brien", 'password' => "p'w", 'password2' => "p'w", 'email' => "a'b@c.d"),
    '',
    array(
        'success' => true,
        'insert_contains' => array("VALUES(11, 'O''Brien', 'p''w', '1', 'a''b@c.d')"),
    )
);

run_case(
    'lookup failure',
    array('mode' => '5'),
    array('username' => 'NewPlayer', 'password' => 'secret1', 'password2' => 'secret1'),
    '',
    array(
        'success' => false,
        'exec_fail' => true,
        'error' => 'Der Account konnte nicht erstellt werden!',
    )
);

run_case(
    'low privilege cannot create',
    array('mode' => '5'),
    array('username' => 'NewPlayer', 'password' => 'secret1', 'password2' => 'secret1'),
    '',
    array(
        'success' => false,
        'right' => 1,
        'no_odbc' => true,
    )
);

run_case(
    'get form',
    array('mode' => '5'),
    array(),
    '',
    array(
        'success' => false,
        'shows_form' => true,
        'no_odbc' => true,
    )
);

if ($failures > 0) {
    fwrite(STDERR, "$failures assertion(s) failed\n");
    exit(1);
}
fwrite(STDOUT, "all account creation checks passed\n");
exit(0);
