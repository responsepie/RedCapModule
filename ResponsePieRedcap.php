<?php

namespace Ossillate\ResponsePieRedcap;

use ExternalModules\AbstractExternalModule;

class ResponsePieRedcap extends AbstractExternalModule
{
    private const ALLOWED_PATH_PREFIX = '/static/take-redcap/';

    /**
     * Loads ResponsePie at the top of every public survey page.
     *
     * This hook runs only in the public survey context. It does not load the
     * script on REDCap project-management or authenticated data-entry pages.
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

        if ($embedCode === '' || strlen($embedCode) > 10000) {
            return;
        }

        // Require one external script element with no inline JavaScript.
        if (!preg_match('/^\s*<script\b[^>]*>\s*<\/script>\s*$/is', $embedCode)) {
            return;
        }

        $scriptUrl = $this->getAttribute($embedCode, 'src');
        $studyToken = $this->getAttribute($embedCode, 'data-study-token');

        if ($scriptUrl === null || $studyToken === null || $studyToken === '') {
            return;
        }

        $urlParts = parse_url($scriptUrl);
        $scheme = isset($urlParts['scheme']) ? strtolower($urlParts['scheme']) : '';
        $host = isset($urlParts['host']) ? strtolower($urlParts['host']) : '';
        $path = isset($urlParts['path']) ? $urlParts['path'] : '';

        // Only allow the official RP REDCap JavaScript endpoint. The module
        // reconstructs the tag instead of printing user-supplied HTML.
        if (
            $scheme !== 'https'
            || strpos($path, self::ALLOWED_PATH_PREFIX) !== 0
            || substr($path, -3) !== '.js'
        ) {
            return;
        }

        $safeUrl = htmlspecialchars($scriptUrl, ENT_QUOTES, 'UTF-8');
        $safeToken = htmlspecialchars($studyToken, ENT_QUOTES, 'UTF-8');

        echo '<script id="rp-redcap-script" type="text/javascript" src="'
            . $safeUrl
            . '" data-study-token="'
            . $safeToken
            . '"></script>';
    }

    /**
     * Returns a quoted HTML attribute from the RP embed line.
     */
    private function getAttribute($html, $attributeName)
    {
        $quotedName = preg_quote($attributeName, '/');
        $pattern = '/\\b' . $quotedName . '\\s*=\\s*(["\'])(.*?)\\1/is';

        if (!preg_match($pattern, $html, $matches)) {
            return null;
        }

        return html_entity_decode($matches[2], ENT_QUOTES, 'UTF-8');
    }
}
