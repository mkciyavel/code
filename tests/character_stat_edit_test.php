<?php
/**
 * Mode 8 actions 1-5 must not write a blank stat.
 * SQL Server stores '' in an integer column as 0, and these forms used to
 * post that empty string whenever a GM submitted the box without a number.
 *
 * Run: php tests/character_stat_edit_test.php
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

    $dir = sys_get_temp_dir() . '/stattest_' . bin2hex(random_bytes(4));
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
    $trim = ltrim($sql);
    if (stripos($trim, 'SELECT ') === 0) {
        if (getenv('TEST_LOOKUP_FAIL') === '1') {
            return false;
        }
        if (getenv('TEST_NO_ROW') === '1') {
            return 'empty';
        }
        return 'row';
    }
    if (stripos($trim, 'UPDATE ') === 0) {
        return getenv('TEST_UPDATE_FAIL') === '1' ? false : 'updated';
    }
    return 'other';
}
function odbc_num_rows($result) {
    return 0;
}
function odbc_fetch_array($result) {
    if ($result !== 'row') {
        return false;
    }
    $column = getenv('TEST_COLUMN');
    $current = getenv('TEST_CURRENT');
    if ($current === 'NULL') {
        $current = null;
    }
    return array($column => $current);
}
PHP;
    file_put_contents($dir . '/includes/config.php', $config);
    file_put_contents($dir . '/includes/head.php', "<?php\n");
    file_put_contents($dir . '/includes/foot.php', "<?php\n");
    symlink($admin, $dir . '/admin.php');

    $runner = $dir . '/runner.php';
    file_put_contents($runner, <<<'PHP'
<?php
ini_set('session.save_path', sys_get_temp_dir());
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
    $env['TEST_COLUMN'] = isset($expect['column']) ? $expect['column'] : 'bLevel';
    $env['TEST_CURRENT'] = array_key_exists('current', $expect) ? (string)$expect['current'] : '80';
    $env['TEST_LOOKUP_FAIL'] = !empty($expect['lookup_fail']) ? '1' : '0';
    $env['TEST_NO_ROW'] = !empty($expect['no_row']) ? '1' : '0';
    $env['TEST_UPDATE_FAIL'] = !empty($expect['update_fail']) ? '1' : '0';

    $procEnv = array();
    foreach ($env as $key => $value) {
        $procEnv[$key] = $value;
    }

    $cmd = 'php -d auto_prepend_file= ' . escapeshellarg($runner);
    $descriptors = array(
        0 => array('pipe', 'r'),
        1 => array('pipe', 'w'),
        2 => array('pipe', 'w'),
    );
    $proc = proc_open($cmd, $descriptors, $pipes, $dir, $procEnv);
    if (!is_resource($proc)) {
        assert_true(false, "$name: runner failed to start");
        return;
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($proc);

    $html = file_exists($dir . '/out.html') ? file_get_contents($dir . '/out.html') : '';
    $sqlLog = file_get_contents($log);
    $updates = array();
    foreach (explode("\n----\n", $sqlLog) as $statement) {
        if (stripos(ltrim($statement), 'UPDATE ') === 0) {
            $updates[] = trim($statement);
        }
    }

    assert_true($exit === 0, "$name exits 0 (stderr: " . trim($stderr) . " stdout: " . trim($stdout) . ")");

    if (!empty($expect['update'])) {
        assert_true(count($updates) === 1, "$name writes exactly one UPDATE, got " . count($updates) . ": " . implode(' | ', $updates));
        if (count($updates) === 1) {
            assert_true($updates[0] === $expect['update'], "$name UPDATE is [" . $updates[0] . "] expected [" . $expect['update'] . "]");
        }
        assert_true(strpos($html, 'erfolgreich') !== false, "$name reports success");
    } else if (!empty($expect['update_fail'])) {
        assert_true(count($updates) === 1, "$name still sends the UPDATE, got " . count($updates) . ": " . implode(' | ', $updates));
        assert_true(strpos($html, 'erfolgreich') === false, "$name does not claim success when the write fails");
    } else {
        assert_true(count($updates) === 0, "$name does not UPDATE, got: " . implode(' | ', $updates));
        assert_true(strpos($html, 'erfolgreich') === false, "$name does not claim success");
    }

    if (!empty($expect['html'])) {
        assert_true(strpos($html, $expect['html']) !== false, "$name HTML contains " . $expect['html']);
    }
    if (!empty($expect['html_absent'])) {
        assert_true(strpos($html, $expect['html_absent']) === false, "$name HTML omits " . $expect['html_absent']);
    }
    if (!empty($expect['select_has'])) {
        assert_true(strpos($sqlLog, $expect['select_has']) !== false, "$name lookup contains " . $expect['select_has']);
    }

    if (!empty($expect['keep'])) {
        copy($dir . '/out.html', $expect['keep']);
        copy($log, $expect['keep'] . '.sql.log');
    }
    unlink($dir . '/out.html');
    unlink($log);
    unlink($runner);
    unlink($dir . '/admin.php');
    unlink($dir . '/includes/config.php');
    unlink($dir . '/includes/head.php');
    unlink($dir . '/includes/foot.php');
    rmdir($dir . '/includes');
    rmdir($dir);
}

$levelBlank = 'Das Level muss als ganze Zahl angegeben werden. Ein leeres Feld w&uuml;rde das Level auf 0 setzen.';

run_case(
    'blank level',
    array('mode' => '8', 'action' => '5', 'cid' => '7'),
    array('newlvl' => ''),
    array(
        'column' => 'bLevel',
        'current' => '80',
        'html' => $levelBlank,
        'select_has' => "SELECT bLevel FROM TGAME_GSP.dbo.TCHARTABLE WHERE dwCharID = '7'",
        'keep' => '/opt/cursor/artifacts/stat-blank-level.html',
    )
);

run_case(
    'whitespace level',
    array('mode' => '8', 'action' => '5', 'cid' => '7'),
    array('newlvl' => '   '),
    array('column' => 'bLevel', 'current' => '80', 'html' => $levelBlank)
);

run_case(
    'quoted level',
    array('mode' => '8', 'action' => '5', 'cid' => '7'),
    array('newlvl' => "1' OR '1'='1"),
        array('column' => 'bLevel', 'current' => '80', 'html' => $levelBlank)
);

run_case(
    'level set',
    array('mode' => '8', 'action' => '5', 'cid' => '7'),
    array('newlvl' => '42'),
    array(
        'column' => 'bLevel',
        'current' => '80',
        'update' => "UPDATE TGAME_GSP.dbo.TCHARTABLE SET bLevel = '42' WHERE dwCharID = '7'",
        'keep' => '/opt/cursor/artifacts/stat-level-set.html',
    )
);

run_case(
    'level zero',
    array('mode' => '8', 'action' => '5', 'cid' => '7'),
    array('newlvl' => '0'),
    array(
        'column' => 'bLevel',
        'current' => '80',
        'update' => "UPDATE TGAME_GSP.dbo.TCHARTABLE SET bLevel = '0' WHERE dwCharID = '7'",
    )
);

run_case(
    'padded level',
    array('mode' => '8', 'action' => '5', 'cid' => '7'),
    array('newlvl' => '  42  '),
    array(
        'column' => 'bLevel',
        'current' => '80',
        'update' => "UPDATE TGAME_GSP.dbo.TCHARTABLE SET bLevel = '42' WHERE dwCharID = '7'",
    )
);

run_case(
    'level form prefills',
    array('mode' => '8', 'action' => '5', 'cid' => '7'),
    array(),
    array(
        'column' => 'bLevel',
        'current' => '80',
        'html' => 'value="80"',
        'select_has' => "SELECT bLevel FROM TGAME_GSP.dbo.TCHARTABLE WHERE dwCharID = '7'",
    )
);

run_case(
    'bad character id',
    array('mode' => '8', 'action' => '5', 'cid' => "7' OR 1=1"),
    array('newlvl' => '42'),
    array('column' => 'bLevel', 'html' => 'Der Charakter existiert nicht!')
);

run_case(
    'missing character',
    array('mode' => '8', 'action' => '5', 'cid' => '7'),
    array('newlvl' => '42'),
    array('column' => 'bLevel', 'no_row' => true, 'html' => 'Das Level konnte nicht ge&auml;ndert werden!')
);

run_case(
    'lookup failure',
    array('mode' => '8', 'action' => '5', 'cid' => '7'),
    array('newlvl' => '42'),
    array('column' => 'bLevel', 'lookup_fail' => true, 'html' => 'Das Level konnte nicht ge&auml;ndert werden!')
);

run_case(
    'update failure',
    array('mode' => '8', 'action' => '5', 'cid' => '7'),
    array('newlvl' => '42'),
    array('column' => 'bLevel', 'current' => '80', 'update_fail' => true, 'html' => 'Das Level konnte nicht ge&auml;ndert werden!')
);

run_case(
    'low privilege',
    array('mode' => '8', 'action' => '5', 'cid' => '7'),
    array('newlvl' => '1'),
    array('column' => 'bLevel', 'current' => '80', 'right' => '1')
);

run_case(
    'blank honor',
    array('mode' => '8', 'action' => '1', 'cid' => '9'),
    array('newtp' => ''),
    array(
        'column' => 'dwTotalPoint',
        'current' => '1500',
        'html' => 'Ein leeres Feld w&uuml;rde den Wert auf 0 setzen.',
        'select_has' => "SELECT dwTotalPoint FROM TGAME_GSP.dbo.TPVPOINTTABLE WHERE dwCharID = '9'",
    )
);

run_case(
    'honor set',
    array('mode' => '8', 'action' => '1', 'cid' => '9'),
    array('newtp' => '1500'),
    array(
        'column' => 'dwTotalPoint',
        'current' => '10',
        'update' => "UPDATE TGAME_GSP.dbo.TPVPOINTTABLE SET dwTotalPoint = '1500' WHERE dwCharID = '9'",
    )
);

run_case(
    'blank usable points',
    array('mode' => '8', 'action' => '2', 'cid' => '9'),
    array('newup' => ''),
    array(
        'column' => 'dwUseablePoint',
        'current' => '40',
        'html' => 'Ein leeres Feld w&uuml;rde den Wert auf 0 setzen.',
        'select_has' => "SELECT dwUseablePoint FROM TGAME_GSP.dbo.TPVPOINTTABLE WHERE dwCharID = '9'",
    )
);

run_case(
    'usable points set',
    array('mode' => '8', 'action' => '2', 'cid' => '9'),
    array('newup' => '40'),
    array(
        'column' => 'dwUseablePoint',
        'current' => '1',
        'update' => "UPDATE TGAME_GSP.dbo.TPVPOINTTABLE SET dwUseablePoint = '40' WHERE dwCharID = '9'",
    )
);

run_case(
    'blank exp',
    array('mode' => '8', 'action' => '3', 'cid' => '4'),
    array('newexp' => ''),
    array(
        'column' => 'dwExp',
        'current' => '250000',
        'html' => 'Ein leeres Feld w&uuml;rde den Wert auf 0 setzen.',
        'select_has' => "SELECT dwExp FROM TGAME_GSP.dbo.TCHARTABLE WHERE dwCharID = '4'",
    )
);

run_case(
    'exp set',
    array('mode' => '8', 'action' => '3', 'cid' => '4'),
    array('newexp' => '250000'),
    array(
        'column' => 'dwExp',
        'current' => '10',
        'update' => "UPDATE TGAME_GSP.dbo.TCHARTABLE SET dwExp = '250000' WHERE dwCharID = '4'",
    )
);

run_case(
    'blank skill points',
    array('mode' => '8', 'action' => '4', 'cid' => '4'),
    array('newsp' => ''),
    array(
        'column' => 'wSkillPoint',
        'current' => '12',
        'html' => 'Ein leeres Feld w&uuml;rde den Wert auf 0 setzen.',
        'select_has' => "SELECT wSkillPoint FROM TGAME_GSP.dbo.TCHARTABLE WHERE dwCharID = '4'",
    )
);

run_case(
    'skill points set',
    array('mode' => '8', 'action' => '4', 'cid' => '4'),
    array('newsp' => '12'),
    array(
        'column' => 'wSkillPoint',
        'current' => '3',
        'update' => "UPDATE TGAME_GSP.dbo.TCHARTABLE SET wSkillPoint = '12' WHERE dwCharID = '4'",
    )
);

run_case(
    'huge level rejected',
    array('mode' => '8', 'action' => '5', 'cid' => '7'),
    array('newlvl' => '9999999999'),
    array('column' => 'bLevel', 'current' => '80', 'html' => $levelBlank)
);

if ($failures > 0) {
    fwrite(STDERR, "$failures assertion(s) failed\n");
    exit(1);
}

fwrite(STDOUT, "All character stat edit assertions passed\n");
exit(0);
