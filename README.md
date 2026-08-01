# ResponsePie for REDCap

This REDCap External Module loads ResponsePie on every public survey page in
an enabled REDCap project. Researchers do not need to add JavaScript to
individual instruments or fields.

## What the module loads

For the bundled study token, the module produces:

```html
<script type="text/javascript" src="https://studies.surveyproctor.com/static/take-redcap/v7/e-348053a44253d4cd2ba8e1b0a79b02ed.js" data-study-token="e-348053a44253d4cd2ba8e1b0a79b02ed"></script>
```

The script is loaded only on public survey pages. It is not loaded on the
Online Designer, project-management pages, reports, or authenticated data-entry
forms.

## Installation

Installation requires a REDCap administrator.

1. Upload or copy the `response_pie_redcap_v1.0.0` folder into REDCap's
   `modules` directory.
2. In the REDCap Control Center, open **Manage External Modules** and enable
   **ResponsePie for REDCap**.
3. Open the REDCap project and select **Manage External Modules**.
4. Enable **ResponsePie for REDCap** for the project.
5. Select **Configure** beside the module.
6. Check **Load ResponsePie on this project's surveys**.
7. Either enter the project's ResponsePie study token or check
   **Use the bundled study token when the token field is empty**.
8. Save the module settings and test the survey using its public survey link.

## Configuration

The bundled token is:

`e-348053a44253d4cd2ba8e1b0a79b02ed`

For another ResponsePie study, enter its token in the project settings. The
token must begin with `e-` and contain 32 lowercase hexadecimal characters.

## Verification

Open the survey's public link, then use the browser's developer tools:

1. Open the **Network** panel.
2. Reload the survey.
3. Search for `surveyproctor` or the study token.
4. Confirm that the JavaScript request returns a successful response.
5. Complete a test response and confirm that it appears in ResponsePie.

## Administrator notes

- The REDCap server must be able to serve survey pages that reference
  `https://studies.surveyproctor.com`.
- If the institution uses a Content Security Policy, the administrator may
  need to allow this domain under the applicable script and connection
  directives.
- Enabling an empty module does not inject the script. Project settings must
  also be configured.
- The study token is visible in the participant's page source because the RP
  browser script requires it. It should be treated as a study identifier, not
  as a secret credential.
- Test the module in a REDCap development or staging environment before
  production use.

## Compatibility

- REDCap 10.0.0 or later
- PHP 7.2 or later
- External Module Framework 14 or later

The module uses REDCap's `redcap_survey_page_top` hook.
