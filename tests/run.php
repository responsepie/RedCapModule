<?php

namespace ExternalModules {
    class AbstractExternalModule
    {
        protected $testProjectSetting = '';

        public function getProjectSetting($key)
        {
            if ($key !== 'rp-embed-code') {
                throw new \RuntimeException('Unexpected project setting: ' . $key);
            }

            return $this->testProjectSetting;
        }
    }
}

namespace {
    require dirname(__DIR__) . '/ResponsePieRedcap.php';

    class TestModule extends \Ossillate\ResponsePieRedcap\ResponsePieRedcap
    {
        public function render($embedCode)
        {
            $this->testProjectSetting = $embedCode;
            ob_start();
            $this->redcap_survey_page_top(1);
            return ob_get_clean();
        }
    }

    $module = new TestModule();
    $baseUrl = 'https://studies.surveyproctor.com/static/take-redcap/study.js';
    $valid = '<script src="' . $baseUrl
        . '" data-study-token="example-token"></script>';
    $expected = '<script src="' . $baseUrl
        . '" data-study-token="example-token"></script>';
    $integrity = 'sha384-' . str_repeat('A', 64);
    $tests = [];

    $tests['valid generated embed line'] = function () use ($module, $valid, $expected) {
        assertSame($expected, $module->render($valid));
    };

    $tests['single-quoted attributes'] = function () use ($module, $baseUrl, $expected) {
        assertSame(
            $expected,
            $module->render("<script src='$baseUrl' data-study-token='example-token'></script>")
        );
    };

    $tests['double-quoted optional attributes'] = function () use (
        $module,
        $baseUrl,
        $integrity
    ) {
        $input = '<script type="text/javascript" src="' . $baseUrl
            . '" data-study-token="example-token" integrity="' . $integrity
            . '" crossorigin="anonymous"></script>';
        $output = '<script src="' . $baseUrl
            . '" data-study-token="example-token" integrity="' . $integrity
            . '" crossorigin="anonymous"></script>';
        assertSame($output, $module->render($input));
    };

    $rejected = [
        'missing token' => '<script src="' . $baseUrl . '"></script>',
        'empty token' => '<script src="' . $baseUrl . '" data-study-token=""></script>',
        'missing script URL' => '<script data-study-token="example-token"></script>',
        'incorrect hostname' => '<script src="https://evil.example/static/take-redcap/study.js" data-study-token="example-token"></script>',
        'HTTP rather than HTTPS' => '<script src="http://studies.surveyproctor.com/static/take-redcap/study.js" data-study-token="example-token"></script>',
        'incorrect path' => '<script src="https://studies.surveyproctor.com/static/other/study.js" data-study-token="example-token"></script>',
        'path traversal' => '<script src="https://studies.surveyproctor.com/static/take-redcap/../evil.js" data-study-token="example-token"></script>',
        'non-JavaScript URL' => '<script src="https://studies.surveyproctor.com/static/take-redcap/study.css" data-study-token="example-token"></script>',
        'unexpected port' => '<script src="https://studies.surveyproctor.com:443/static/take-redcap/study.js" data-study-token="example-token"></script>',
        'user-information URL trick' => '<script src="https://studies.surveyproctor.com@evil.example/static/take-redcap/study.js" data-study-token="example-token"></script>',
        'data-src is not src' => '<script data-src="' . $baseUrl . '" data-study-token="example-token"></script>',
        'inline JavaScript' => '<script src="' . $baseUrl . '" data-study-token="example-token">alert(1)</script>',
        'multiple script tags' => $valid . $valid,
        'malformed integrity' => '<script src="' . $baseUrl . '" data-study-token="example-token" integrity="sha384-not-a-digest" crossorigin="anonymous"></script>',
        'incorrect crossorigin' => '<script src="' . $baseUrl . '" data-study-token="example-token" crossorigin="use-credentials"></script>',
        'event-handler attribute' => '<script src="' . $baseUrl . '" data-study-token="example-token" onload="alert(1)"></script>',
        'fragment' => '<script src="' . $baseUrl . '#fragment" data-study-token="example-token"></script>',
        'query string' => '<script src="' . $baseUrl . '?x=1" data-study-token="example-token"></script>',
        'encoded path trick' => '<script src="https://studies.surveyproctor.com/static/take-redcap/%2e%2e/evil.js" data-study-token="example-token"></script>',
        'duplicate src' => '<script src="' . $baseUrl . '" src="' . $baseUrl . '" data-study-token="example-token"></script>',
    ];

    foreach ($rejected as $name => $input) {
        $tests[$name] = function () use ($module, $input) {
            assertSame('', $module->render($input));
        };
    }

    $tests['HTML-escaping attempt is inert'] = function () use ($module, $baseUrl) {
        $input = '<script src="' . $baseUrl
            . '" data-study-token="safe&amp;quot; onload=&amp;quot;alert(1)"></script>';
        $expectedOutput = '<script src="' . $baseUrl
            . '" data-study-token="safe&amp;quot; onload=&amp;quot;alert(1)"></script>';
        assertSame($expectedOutput, $module->render($input));
    };

    $failures = 0;
    foreach ($tests as $name => $test) {
        try {
            $test();
            echo "PASS: $name\n";
        } catch (\Throwable $error) {
            ++$failures;
            fwrite(STDERR, "FAIL: $name\n  " . $error->getMessage() . "\n");
        }
    }

    if ($failures > 0) {
        exit(1);
    }

    echo 'All ' . count($tests) . " tests passed.\n";

    function assertSame($expected, $actual)
    {
        if ($expected !== $actual) {
            throw new \RuntimeException(
                'Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)
            );
        }
    }
}
