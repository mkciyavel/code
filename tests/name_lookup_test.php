<?php
/**
 * Account search, character search, and GM-equipment must keep apostrophes
 * inside SQL Server string literals and must not fatal when the lookup fails.
 * Run: php tests/name_lookup_test.php
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

    $dir = sys_get_temp_dir() . '/namelookup_' . bin2hex(random_bytes(4));
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

class LookupResult {
    public $rows;
    public $i = 0;
    public function __construct($rows) { $this->rows = $rows; }
}

function odbc_exec($con, $sql) {
    file_put_contents(getenv('ODBC_LOG'), $sql . "\n----\n", FILE_APPEND);
    if (getenv('TEST_EXEC_FAIL') === '1' && stripos($sql, 'EXEC ') !== false) {
        return false;
    }
    if (getenv('TEST_LOOKUP_FAIL') === '1' && stripos($sql, 'TCURRENTUSER') === false && stripos($sql, 'EXEC ') === false) {
        return false;
    }
    if (stripos($sql, 'TCURRENTUSER') !== false) {
        if (getenv('TEST_ONLINE') === '1') {
            return new LookupResult(array(array('dwCharID' => 9)));
        }
        return new LookupResult(array());
    }
    if (stripos($sql, 'TUSERPROTECTED') !== false) {
        return new LookupResult(array());
    }
    if (stripos($sql, 'TCASHTESTTABLE') !== false) {
        return new LookupResult(array(array('dwCash' => 12, 'dwBonus' => 3)));
    }
    if (stripos($sql, 'SELECT szName FROM TGAME_GSP.dbo.TCHARTABLE') !== false) {
        return new LookupResult(array());
    }
    if (stripos($sql, 'SELECT dwUserID FROM TGLOBAL_GSP.dbo.TACCOUNT') !== false) {
        if (getenv('TEST_MISSING') === '1') {
            return new LookupResult(array());
        }
        return new LookupResult(array(array('dwUserID' => 5)));
    }
    if (stripos($sql, 'SELECT szUserID FROM TGLOBAL_GSP.dbo.TACCOUNT') !== false) {
        if (getenv('TEST_MISSING') === '1') {
            return new LookupResult(array());
        }
        return new LookupResult(array(array('szUserID' => "O'Brien")));
    }
    if (stripos($sql, 'SELECT dwCharID FROM TGAME_GSP.dbo.TCHARTABLE') !== false) {
        if (getenv('TEST_MISSING') === '1') {
            return new LookupResult(array());
        }
        return new LookupResult(array(array('dwCharID' => 9)));
    }
    if (stripos($sql, 'LIKE') !== false && stripos($sql, 'SELECT dwUserID') !== false) {
        $n = (int)getenv('TEST_COUNT');
        if ($n < 1) {
            $n = 1;
        }
        if (getenv('TEST_MISSING') === '1') {
            $n = 0;
        }
        $rows = array();
        for ($i = 0; $i < $n; $i++) {
            $rows[] = array('dwUserID' => $i + 1);
        }
        return new LookupResult($rows);
    }
    if (stripos($sql, 'LIKE') !== false) {
        if (getenv('TEST_MISSING') === '1') {
            return new LookupResult(array());
        }
        return new LookupResult(array(array(
            'dwCharID' => 9,
            'szName' => "Da'Rosh",
            'bSex' => 0,
            'bCountry' => 0,
            'bRace' => 0,
            'bClass' => 0,
            'bHair' => 0,
            'bFace' => 0,
            'bLevel' => 10,
        )));
    }
    if (stripos($sql, 'EXEC ') !== false) {
        return 'exec-ok';
    }
    return new LookupResult(array());
}

function odbc_num_rows($result) {
    if (!$result instanceof LookupResult) {
        throw new TypeError('odbc_num_rows(): Argument #1 ($statement) must be of type Odbc\\Result, bool given');
    }
    return count($result->rows);
}

function odbc_fetch_array($result) {
    if (!$result instanceof LookupResult) {
        return false;
    }
    if ($result->i >= count($result->rows)) {
        return false;
    }
    return $result->rows[$result->i++];
}

function odbc_free_result($result) {
    return true;
}

function mysql_real_escape_string($string) {
    return addslashes((string)$string);
}
function mysql_query($sql) { return false; }
function mysql_num_rows($result) { return 0; }
function mysql_fetch_assoc($result) { return false; }
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
    $env['ODBC_LOG'] = $log;
    $env['TEST_OUT'] = $dir . '/out.html';
    $env['TEST_GET'] = json_encode($get);
    $env['TEST_POST'] = json_encode($post);
    $env['TEST_RIGHT'] = isset($expect['right']) ? (string)$expect['right'] : '4';
    $env['TEST_MISSING'] = !empty($expect['missing']) ? '1' : '0';
    $env['TEST_LOOKUP_FAIL'] = !empty($expect['lookup_fail']) ? '1' : '0';
    $env['TEST_EXEC_FAIL'] = !empty($expect['exec_fail']) ? '1' : '0';
    $env['TEST_ONLINE'] = !empty($expect['online']) ? '1' : '0';
    $env['TEST_COUNT'] = isset($expect['count']) ? (string)$expect['count'] : '1';

    $cmd = 'php -d auto_prepend_file= ' . escapeshellarg($runner);
    $descriptors = array(1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
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
        assert_true(strpos($html, $expect['success']) !== false, "$name shows {$expect['success']}");
    }
    if (!empty($expect['no_success'])) {
        assert_true(strpos($html, 'erfolgreich vergeben') === false, "$name does not claim GM equipment was given");
    }
    if (isset($expect['html'])) {
        assert_true(strpos($html, $expect['html']) !== false, "$name html contains {$expect['html']}");
    }
    if (isset($expect['html_absent'])) {
        assert_true(strpos($html, $expect['html_absent']) === false, "$name html omits {$expect['html_absent']}");
    }
    if (isset($expect['error'])) {
        assert_true(strpos($html, $expect['error']) !== false, "$name shows error {$expect['error']}");
    }
    if (!empty($expect['sql_contains'])) {
        foreach ($expect['sql_contains'] as $needle) {
            assert_true(strpos($sqlLog, $needle) !== false, "$name SQL contains " . $needle);
        }
    }
    if (!empty($expect['sql_absent'])) {
        foreach ($expect['sql_absent'] as $needle) {
            assert_true(strpos($sqlLog, $needle) === false, "$name SQL omits " . $needle);
        }
    }
    if (!empty($expect['no_odbc'])) {
        assert_true(trim($sqlLog) === '', "$name does not touch the database");
    }
    if (!empty($expect['no_exec'])) {
        assert_true(stripos($sqlLog, 'EXEC ') === false, "$name does not EXEC");
    }

    @unlink($dir . '/admin.php');
    @unlink($runner);
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
    'account search keeps apostrophe',
    array('mode' => '7'),
    array('username' => "O'Brien"),
    array(
        'html' => "O'Brien",
        'html_absent' => 'existiert nicht',
        'sql_contains' => array("szUserID = 'O''Brien'"),
        'sql_absent' => array("\\'"),
    )
);

run_case(
    'account search plain name',
    array('mode' => '7'),
    array('username' => 'Hero'),
    array(
        'html' => 'Hero',
        'sql_contains' => array("szUserID = 'Hero'"),
    )
);

run_case(
    'account search missing name',
    array('mode' => '7'),
    array('username' => "O'Brien"),
    array(
        'missing' => true,
        'error' => 'Der angegebene Account existiert nicht!',
        'html_absent' => 'Account&uuml;bersicht',
        'sql_contains' => array("szUserID = 'O''Brien'"),
    )
);

run_case(
    'account search blank name',
    array('mode' => '7'),
    array('username' => '   '),
    array(
        'error' => 'Der angegebene Account existiert nicht!',
        'no_odbc' => true,
        'html_absent' => 'Account&uuml;bersicht',
    )
);

run_case(
    'account search query failure',
    array('mode' => '7'),
    array('username' => "O'Brien"),
    array(
        'lookup_fail' => true,
        'error' => 'Der angegebene Account existiert nicht!',
        'html_absent' => 'Account&uuml;bersicht',
    )
);

run_case(
    'account open by id',
    array('mode' => '7', 'id' => '5'),
    array(),
    array(
        'html' => "O'Brien",
        'sql_contains' => array('dwUserID = 5'),
        'sql_absent' => array('OR'),
    )
);

run_case(
    'account id cannot break the query',
    array('mode' => '7', 'id' => "5 OR 1=1"),
    array(),
    array(
        'error' => 'Der angegebene Account existiert nicht!',
        'no_odbc' => true,
        'html_absent' => 'Account&uuml;bersicht',
    )
);

run_case(
    'character search keeps apostrophe',
    array('mode' => '8', 's' => "Da'Rosh"),
    array(),
    array(
        'html' => "Da'Rosh",
        'sql_contains' => array("LIKE '%Da''Rosh%'"),
        'sql_absent' => array("\\'", "LIKE '%Da'Rosh%'"),
    )
);

run_case(
    'character search pager keeps the raw name',
    array('mode' => '8', 's' => "Da'Rosh"),
    array(),
    array(
        'count' => 20,
        'html' => 's=Da%27Rosh',
        'html_absent' => "s=Da\\",
    )
);

run_case(
    'character search quote stays inside LIKE',
    array('mode' => '8', 's' => "a' OR szName LIKE '%"),
    array(),
    array(
        'sql_contains' => array("LIKE '%a'' OR szName LIKE ''%%'"),
        'sql_absent' => array("\\'"),
    )
);

run_case(
    'character search query failure',
    array('mode' => '8', 's' => "Da'Rosh"),
    array(),
    array(
        'lookup_fail' => true,
        'html' => 'Keine Ergebnisse!',
    )
);

run_case(
    'gm equipment keeps apostrophe',
    array('mode' => '12'),
    array('username' => "Da'Rosh"),
    array(
        'success' => 'erfolgreich vergeben',
        'sql_contains' => array(
            "szName = 'Da''Rosh'",
            "@szName = 'Da''Rosh'",
        ),
        'sql_absent' => array("szName = 'Da'Rosh'", "\\'"),
    )
);

run_case(
    'gm equipment quote cannot close the string',
    array('mode' => '12'),
    array('username' => "a' OR '1'='1"),
    array(
        'success' => 'erfolgreich vergeben',
        'sql_contains' => array(
            "szName = 'a'' OR ''1''=''1'",
            "@szName = 'a'' OR ''1''=''1'",
        ),
        'sql_absent' => array("szName = 'a' OR"),
    )
);

run_case(
    'gm equipment missing character',
    array('mode' => '12'),
    array('username' => "Da'Rosh"),
    array(
        'missing' => true,
        'no_success' => true,
        'no_exec' => true,
        'error' => 'Der Charakter existiert nicht.',
    )
);

run_case(
    'gm equipment online character',
    array('mode' => '12'),
    array('username' => 'Hero'),
    array(
        'online' => true,
        'no_success' => true,
        'no_exec' => true,
        'error' => 'Der Charakter muss ausgeloggt sein',
    )
);

run_case(
    'gm equipment failed procedure',
    array('mode' => '12'),
    array('username' => "Da'Rosh"),
    array(
        'exec_fail' => true,
        'no_success' => true,
        'error' => 'Das GM-Equipment konnte nicht vergeben werden.',
        'sql_contains' => array("@szName = 'Da''Rosh'"),
    )
);

run_case(
    'gm equipment blank name',
    array('mode' => '12'),
    array('username' => '  '),
    array(
        'no_success' => true,
        'no_odbc' => true,
        'error' => 'Der Charakter existiert nicht.',
    )
);

run_case(
    'gm equipment low privilege',
    array('mode' => '12'),
    array('username' => "Da'Rosh"),
    array(
        'right' => 1,
        'no_success' => true,
        'no_odbc' => true,
    )
);

if ($failures > 0) {
    fwrite(STDERR, "$failures assertion(s) failed\n");
    exit(1);
}
fwrite(STDOUT, "all name lookup checks passed\n");
exit(0);
