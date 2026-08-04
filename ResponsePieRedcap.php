<?php

namespace Ossillate\ResponsePieRedcap;

use ExternalModules\AbstractExternalModule;

class ResponsePieRedcap extends AbstractExternalModule
{
    private const ALLOWED_HOST = 'studies.surveyproctor.com';
    private const ALLOWED_PATH_PREFIX = '/static/take-redcap/';
    private const MAX_EMBED_CODE_LENGTH = 10000;
    private const MAX_STUDY_TOKEN_LENGTH = 512;

    /**
     * Adds the validated ResponsePie script to REDCap survey pages.
     *
     * The hook only reads this module's project setting. It does not read
     * REDCap records or use the REDCap database or API.
     */
    public function redcap_survey_page_top(
        $project_id,
        $record = null,
        $instrument = null,
        $event_id = null,
        $group_id = null,
        $survey_hash = null,
        $response_id = null,
        $repeat_instance = 1
    ) {
        $embedCode = trim((string) $this->getProjectSetting('rp-embed-code'));
        $attributes = $this->parseEmbedCode($embedCode);

        if ($attributes === null) {
            return;
        }

        echo '<script src="' . $this->escape($attributes['src']) . '"'
            . ' data-study-token="' . $this->escape($attributes['data-study-token']) . '"';

        if (isset($attributes['integrity'])) {
            echo ' integrity="' . $this->escape($attributes['integrity']) . '"';
        }

        if (isset($attributes['crossorigin'])) {
            echo ' crossorigin="anonymous"';
        }

        echo '></script>';
    }

    /**
     * Parses exactly one external script element and returns safe attributes.
     */
    private function parseEmbedCode($embedCode)
    {
        if (
            $embedCode === ''
            || strlen($embedCode) > self::MAX_EMBED_CODE_LENGTH
            || preg_match('/[\x00-\x1F\x7F]/', $embedCode)
        ) {
            return null;
        }

        if (!preg_match('/^<script\s+([^<>]*)>\s*<\/script>$/iD', $embedCode, $tagMatch)) {
            return null;
        }

        $attributeText = $tagMatch[1];
        $attributes = [];
        $offset = 0;
        $length = strlen($attributeText);

        while ($offset < $length) {
            if (!preg_match('/\S/', substr($attributeText, $offset))) {
                break;
            }

            if (!preg_match(
                '/\G\s*([A-Za-z_:][A-Za-z0-9_.:-]*)\s*=\s*(["\'])(.*?)\2/s',
                $attributeText,
                $attributeMatch,
                0,
                $offset
            )) {
                return null;
            }

            $name = strtolower($attributeMatch[1]);
            if (isset($attributes[$name])) {
                return null;
            }

            $attributes[$name] = html_entity_decode(
                $attributeMatch[3],
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            );
            $offset += strlen($attributeMatch[0]);
        }

        if (preg_match('/\S/', substr($attributeText, $offset))) {
            return null;
        }

        $allowedNames = ['src', 'data-study-token', 'integrity', 'crossorigin', 'type'];
        if (array_diff(array_keys($attributes), $allowedNames)) {
            return null;
        }

        if (isset($attributes['type']) && strtolower($attributes['type']) !== 'text/javascript') {
            return null;
        }

        if (!$this->isValidScriptUrl(isset($attributes['src']) ? $attributes['src'] : null)) {
            return null;
        }

        if (!$this->isValidStudyToken(
            isset($attributes['data-study-token']) ? $attributes['data-study-token'] : null
        )) {
            return null;
        }

        if (
            isset($attributes['integrity'])
            && !preg_match('/^sha384-[A-Za-z0-9+\/]{64}$/D', $attributes['integrity'])
        ) {
            return null;
        }

        if (
            isset($attributes['crossorigin'])
            && strtolower($attributes['crossorigin']) !== 'anonymous'
        ) {
            return null;
        }

        return $attributes;
    }

    private function isValidScriptUrl($url)
    {
        if (
            !is_string($url)
            || $url === ''
            || preg_match('/[\x00-\x20\x7F]/', $url)
            || filter_var($url, FILTER_VALIDATE_URL) === false
        ) {
            return false;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }

        if (
            !isset($parts['scheme'], $parts['host'], $parts['path'])
            || strtolower($parts['scheme']) !== 'https'
            || strtolower($parts['host']) !== self::ALLOWED_HOST
            || isset($parts['port'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])
            || isset($parts['query'])
            || strpos($parts['path'], self::ALLOWED_PATH_PREFIX) !== 0
            || strlen($parts['path']) <= strlen(self::ALLOWED_PATH_PREFIX)
            || substr($parts['path'], -3) !== '.js'
            || strpos($parts['path'], '%') !== false
            || strpos($parts['path'], '\\') !== false
            || preg_match('#/(?:\.|\.\.)(?:/|$)#', $parts['path'])
        ) {
            return false;
        }

        return true;
    }

    private function isValidStudyToken($token)
    {
        return is_string($token)
            && $token !== ''
            && strlen($token) <= self::MAX_STUDY_TOKEN_LENGTH
            && !preg_match('/[\x00-\x1F\x7F]/', $token);
    }

    private function escape($value)
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
